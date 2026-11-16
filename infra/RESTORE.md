# Bring CampusFlow back on Azure (with data)

Use this to move from the old `campusflow-demo-rg` to a fresh, "demo"-free setup. Part A must be done **before** the old group is deleted.
Commands are PowerShell, run from the repo root. Replace `<...>` values.

New names created by `infra/main.bicep`:

| Item | Old name | New name |
| --- | --- | --- |
| Resource group | `campusflow-demo-rg` | `campusflow-rg` |
| Container App | `campusflow-demo` | `campusflow` |
| Environment | `campusflow-demo-env` | `campusflow-env` |
| Registry | `cfdemoacr31512141` | `campusflowacr` |
| MySQL server | `cf-demo-db-3151214110` | `campusflow-db` |
| Seed job | `cf-demo-seed` | `campusflow-seed` |
| Log workspace | `workspace-campusflowdemorgQK7H` | `campusflow-logs` |
| Image | `campusflow-demo` | `campusflow` |

> The full recreate has not been tested end to end yet. The new names do not clash with the old ones, so you can
> deploy the new group **next to** the old one first, check it works, and only then delete the old group.

## Part A. Before deleting the old resource group

1. **Save the secrets** in a password manager. Keep the same `APP_KEY`, or encrypted data will not decrypt.

   ```powershell
   az containerapp secret show -g campusflow-demo-rg -n campusflow-demo --secret-name app-key --query value -o tsv
   az containerapp secret show -g campusflow-demo-rg -n campusflow-demo --secret-name db-password --query value -o tsv
   ```

2. **Make sure the old MySQL server is running** (a stopped server cannot be dumped).

   ```powershell
   az mysql flexible-server start -g campusflow-demo-rg -n cf-demo-db-3151214110
   ```

3. **Allow your public IP** through its firewall.

   ```powershell
   $ip = (Invoke-RestMethod https://api.ipify.org)
   az mysql flexible-server firewall-rule create -g campusflow-demo-rg -n cf-demo-db-3151214110 --rule-name my-ip --start-ip-address $ip --end-ip-address $ip
   ```

4. **Dump the database** to a folder outside the repo (it contains real data, so never commit it).

   ```powershell
   New-Item -ItemType Directory -Force D:\Backups\campusflow | Out-Null
   docker run --rm -e MYSQL_PWD=<db-password> -v D:\Backups\campusflow:/backup mysql:8.0 sh -c "mysqldump -h cf-demo-db-3151214110.mysql.database.azure.com -u cfadmin --ssl-mode=REQUIRED --single-transaction --no-tablespaces campusflow > /backup/campusflow-backup.sql"
   ```

5. **Check the dump** is not empty: `Get-Item D:\Backups\campusflow\campusflow-backup.sql` should be well over a few KB.
6. Keep a copy of the backup somewhere else too (cloud drive or another disk).
7. Make sure `infra/` is committed and pushed.

Delete the old group only after the new one works (Part E): `az group delete -n campusflow-demo-rg`.

## Part B. Create the new infrastructure

1. Refresh PATH (the winget Bicep is not found by `az`) and compile:

   ```powershell
   $env:Path = [Environment]::GetEnvironmentVariable('Path','Machine') + ';' + [Environment]::GetEnvironmentVariable('Path','User')
   bicep build infra/main.bicep --outfile $env:TEMP\cf-main.json
   ```

2. Create the group and deploy the infrastructure (no app yet, because the registry is empty):

   ```powershell
   az group create -n campusflow-rg -l centralindia
   az deployment group create -g campusflow-rg -f $env:TEMP\cf-main.json -p dbPassword=<db-password> appKey=<app-key>
   ```

   Note the outputs: `githubClientId` and `registryUsername`.

3. Build the image inside Azure (no local Docker needed):

   ```powershell
   az acr build -r campusflowacr -t campusflow:bootstrap .
   ```

4. Deploy again, this time with the app:

   ```powershell
   az deployment group create -g campusflow-rg -f $env:TEMP\cf-main.json -p dbPassword=<db-password> appKey=<app-key> appImage=campusflowacr.azurecr.io/campusflow:bootstrap
   ```

   The `appUrl` output is the new address (the host name changes with every new environment).

If Azure says a name is still reserved, pick another and pass it as `acrName=...` or `mysqlServerName=...`.

## Part C. Restore the data

1. Allow your IP on the new server (same as A3, using `-g campusflow-rg -n campusflow-db`).
2. Restore the dump:

   ```powershell
   docker run --rm -e MYSQL_PWD=<db-password> -v D:\Backups\campusflow:/backup mysql:8.0 sh -c "mysql -h campusflow-db.mysql.database.azure.com -u cfadmin --ssl-mode=REQUIRED campusflow < /backup/campusflow-backup.sql"
   ```

3. Run any migrations the restored database is missing:

   ```powershell
   az containerapp exec -n campusflow -g campusflow-rg --command "php artisan migrate --force"
   ```

4. Remove your IP rule when finished:

   ```powershell
   az mysql flexible-server firewall-rule delete -g campusflow-rg -n campusflow-db --rule-name my-ip --yes
   ```

**No backup?** Load fresh demo data instead. Build the seed image (it needs the dev dependencies), deploy with
`seedImage=...`, then start the job: `az containerapp job start -g campusflow-rg -n campusflow-seed`.
The data is regenerated, so it will not match the old data.

## Part D. Reconnect GitHub deploys

The workflow `.github/workflows/deploy.yml` still points at the old names. Update it **when you switch over**:

- `registryUrl: campusflowacr.azurecr.io`
- `containerAppName: campusflow`
- `resourceGroup: campusflow-rg`
- `imageToBuild: campusflowacr.azurecr.io/campusflow:${{ github.sha }}`
- rename the secrets it reads from `CAMPUSFLOWDEMO_*` to `CAMPUSFLOW_*`

Then create these in GitHub under Settings → Secrets and variables → Actions (or with `gh secret set <NAME>`):

| Secret | Value |
| --- | --- |
| `CAMPUSFLOW_AZURE_CLIENT_ID` | `githubClientId` output from Part B |
| `CAMPUSFLOW_AZURE_TENANT_ID` | same tenant as before |
| `CAMPUSFLOW_AZURE_SUBSCRIPTION_ID` | same subscription as before |
| `CAMPUSFLOW_REGISTRY_USERNAME` | `registryUsername` output (`campusflowacr`) |
| `CAMPUSFLOW_REGISTRY_PASSWORD` | `az acr credential show -n campusflowacr --query "passwords[0].value" -o tsv` |

Then run the **Deploy** workflow once (Actions tab → Run workflow) to replace the bootstrap image with the current `main`.

## Part E. Check it works

1. Open `<appUrl>/admin/login` and sign in (for example `admin@campusflow.test` / `secret` if you restored demo data).
2. If the page does not load, check the logs:

   ```powershell
   $env:PYTHONIOENCODING = 'utf-8'
   az containerapp logs show -n campusflow -g campusflow-rg --tail 50
   ```

3. The app is set to scale to zero, so the first request after idle takes a few seconds.
4. Files saved on the container's disk (if the app stores any) are not part of the database dump and are lost on deletion.
5. When everything works, delete the old group: `az group delete -n campusflow-demo-rg`.
