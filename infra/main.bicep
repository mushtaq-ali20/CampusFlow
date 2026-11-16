// CampusFlow on Azure Container Apps (resource group campusflow-rg, Central India).
//
// Recreate from nothing (two phases, because the app needs an image in the registry first):
//   az group create -n campusflow-rg -l centralindia
//   export CF_DB_PASSWORD=<password> CF_APP_KEY=<base64:...>      (the same values as before, or new ones)
//   az deployment group create -g campusflow-rg -p infra/main.bicepparam
//   az acr build -r campusflowacr -t campusflow:bootstrap .     (run from the repo root)
//   export CF_APP_IMAGE=campusflowacr.azurecr.io/campusflow:bootstrap
//   az deployment group create -g campusflow-rg -p infra/main.bicepparam
// Then update the GitHub secrets from the outputs (client id, registry username and password) and re-run the
// deploy workflow. The seed job image (CF_SEED_IMAGE) is optional and only needed to load demo data.

targetScope = 'resourceGroup'

param location string = resourceGroup().location

@description('Globally unique registry name (letters and digits only).')
param acrName string = 'campusflowacr'

@description('Globally unique MySQL server name.')
param mysqlServerName string = 'campusflow-db'

param mysqlAdminLogin string = 'cfadmin'

@secure()
param dbPassword string

@secure()
param appKey string

@description('Full app image. Leave empty on the first run: the registry has no image yet, so the app and seed job are skipped.')
param appImage string = ''

@description('Image with dev dependencies (Faker) for the seed job. Leave empty to skip the job.')
param seedImage string = ''

@description('GitHub OIDC subject used by the deploy workflow.')
param githubSubject string = 'repo:mushtaq-ali20@5578059/CampusFlow@1451279932:ref:refs/heads/main'

var appName = 'campusflow'
var dbHost = '${mysqlServerName}.mysql.database.azure.com'
var contributorRoleId = 'b24988ac-6180-42a0-ab88-20f7382dd24c'

resource logs 'Microsoft.OperationalInsights/workspaces@2023-09-01' = {
  name: 'campusflow-logs'
  location: location
  properties: {
    sku: { name: 'PerGB2018' }
    retentionInDays: 7
    workspaceCapping: { dailyQuotaGb: json('0.1') }
  }
}

resource acr 'Microsoft.ContainerRegistry/registries@2023-07-01' = {
  name: acrName
  location: location
  sku: { name: 'Basic' }
  properties: { adminUserEnabled: true }
}

resource mysql 'Microsoft.DBforMySQL/flexibleServers@2023-12-30' = {
  name: mysqlServerName
  location: location
  sku: {
    name: 'Standard_B1ms'
    tier: 'Burstable'
  }
  properties: {
    version: '8.0.21'
    administratorLogin: mysqlAdminLogin
    administratorLoginPassword: dbPassword
    storage: {
      storageSizeGB: 20
      autoGrow: 'Enabled'
      autoIoScaling: 'Enabled'
    }
    backup: {
      backupRetentionDays: 7
      geoRedundantBackup: 'Disabled'
    }
    highAvailability: { mode: 'Disabled' }
    network: { publicNetworkAccess: 'Enabled' }
  }
}

resource allowAzure 'Microsoft.DBforMySQL/flexibleServers/firewallRules@2023-12-30' = {
  parent: mysql
  name: 'AllowAllAzureServicesAndResourcesWithinAzureIps'
  properties: {
    startIpAddress: '0.0.0.0'
    endIpAddress: '0.0.0.0'
  }
}

resource database 'Microsoft.DBforMySQL/flexibleServers/databases@2023-12-30' = {
  parent: mysql
  name: 'campusflow'
  properties: {
    charset: 'utf8mb4'
    collation: 'utf8mb4_unicode_ci'
  }
}

resource env 'Microsoft.App/managedEnvironments@2024-03-01' = {
  name: 'campusflow-env'
  location: location
  properties: {
    appLogsConfiguration: {
      destination: 'log-analytics'
      logAnalyticsConfiguration: {
        customerId: logs.properties.customerId
        sharedKey: logs.listKeys().primarySharedKey
      }
    }
    workloadProfiles: [
      {
        name: 'Consumption'
        workloadProfileType: 'Consumption'
      }
    ]
  }
}

var appEnv = [
  { name: 'APP_ENV', value: 'production' }
  { name: 'APP_DEBUG', value: 'false' }
  { name: 'APP_KEY', secretRef: 'app-key' }
  { name: 'APP_URL', value: 'https://${appName}.${env.properties.defaultDomain}' }
  { name: 'LOG_CHANNEL', value: 'stderr' }
  { name: 'LOG_LEVEL', value: 'warning' }
  { name: 'DB_CONNECTION', value: 'mysql' }
  { name: 'DB_HOST', value: dbHost }
  { name: 'DB_PORT', value: '3306' }
  { name: 'DB_DATABASE', value: 'campusflow' }
  { name: 'DB_USERNAME', value: mysqlAdminLogin }
  { name: 'DB_PASSWORD', secretRef: 'db-password' }
  { name: 'MYSQL_ATTR_SSL_CA', value: '/etc/ssl/certs/ca-certificates.crt' }
  { name: 'SESSION_DRIVER', value: 'database' }
  { name: 'CACHE_STORE', value: 'database' }
  { name: 'TRUSTED_PROXIES', value: '*' }
]

var acrServer = '${acrName}.azurecr.io'

// Port 8080 must match SERVER_NAME, the ingress target port and every probe (probes don't follow the target port).
resource app 'Microsoft.App/containerApps@2024-03-01' = if (!empty(appImage)) {
  name: appName
  location: location
  properties: {
    managedEnvironmentId: env.id
    workloadProfileName: 'Consumption'
    configuration: {
      activeRevisionsMode: 'Single'
      ingress: {
        external: true
        targetPort: 8080
        transport: 'Auto'
        allowInsecure: false
      }
      registries: [
        {
          server: acrServer
          username: acr.listCredentials().username
          passwordSecretRef: 'acr-password'
        }
      ]
      secrets: [
        { name: 'acr-password', value: acr.listCredentials().passwords[0].value }
        { name: 'app-key', value: appKey }
        { name: 'db-password', value: dbPassword }
      ]
    }
    template: {
      containers: [
        {
          name: appName
          image: appImage
          resources: {
            cpu: json('0.5')
            memory: '1Gi'
          }
          env: concat(appEnv, [
            { name: 'APP_NAME', value: 'CampusFlow' }
            { name: 'QUEUE_CONNECTION', value: 'sync' }
            { name: 'SERVER_NAME', value: ':8080' }
          ])
          probes: [
            {
              type: 'Liveness'
              tcpSocket: { port: 8080 }
              periodSeconds: 10
              failureThreshold: 3
              timeoutSeconds: 5
            }
            {
              type: 'Readiness'
              tcpSocket: { port: 8080 }
              periodSeconds: 5
              failureThreshold: 48
              timeoutSeconds: 5
            }
            {
              type: 'Startup'
              tcpSocket: { port: 8080 }
              initialDelaySeconds: 1
              periodSeconds: 1
              failureThreshold: 240
              timeoutSeconds: 3
            }
          ]
        }
      ]
      scale: {
        minReplicas: 0
        maxReplicas: 1
      }
    }
  }
  dependsOn: [ database ]
}

resource seedJob 'Microsoft.App/jobs@2024-03-01' = if (!empty(seedImage)) {
  name: 'campusflow-seed'
  location: location
  properties: {
    environmentId: env.id
    workloadProfileName: 'Consumption'
    configuration: {
      triggerType: 'Manual'
      replicaTimeout: 2400
      replicaRetryLimit: 0
      manualTriggerConfig: {
        parallelism: 1
        replicaCompletionCount: 1
      }
      registries: [
        {
          server: acrServer
          username: acr.listCredentials().username
          passwordSecretRef: 'acr-password'
        }
      ]
      secrets: [
        { name: 'acr-password', value: acr.listCredentials().passwords[0].value }
        { name: 'app-key', value: appKey }
        { name: 'db-password', value: dbPassword }
      ]
    }
    template: {
      containers: [
        {
          name: 'seed'
          image: seedImage
          command: [ '/bin/sh', '-c', 'php artisan migrate --force --seed && php artisan db:seed --class=SchoolDemoSeeder --force' ]
          resources: {
            cpu: json('1.0')
            memory: '2Gi'
          }
          env: concat(appEnv, [
            { name: 'QUEUE_CONNECTION', value: 'database' }
            { name: 'SERVER_NAME', value: ':80' }
          ])
        }
      ]
    }
  }
  dependsOn: [ database ]
}

// Identity the GitHub Actions deploy workflow logs in with (OIDC, no stored password).
resource deployIdentity 'Microsoft.ManagedIdentity/userAssignedIdentities@2023-01-31' = {
  name: 'campusflow-github-oidc'
  location: location
}

resource deployCredential 'Microsoft.ManagedIdentity/userAssignedIdentities/federatedIdentityCredentials@2023-01-31' = {
  parent: deployIdentity
  name: 'campusflow-fed'
  properties: {
    issuer: 'https://token.actions.githubusercontent.com'
    subject: githubSubject
    audiences: [ 'api://AzureADTokenExchange' ]
  }
}

resource deployRole 'Microsoft.Authorization/roleAssignments@2022-04-01' = {
  name: guid(resourceGroup().id, deployIdentity.id, contributorRoleId)
  properties: {
    principalId: deployIdentity.properties.principalId
    principalType: 'ServicePrincipal'
    roleDefinitionId: subscriptionResourceId('Microsoft.Authorization/roleDefinitions', contributorRoleId)
  }
}

@description('GitHub secret CAMPUSFLOW_AZURE_CLIENT_ID')
output githubClientId string = deployIdentity.properties.clientId

@description('GitHub secret CAMPUSFLOW_REGISTRY_USERNAME')
output registryUsername string = acrName

@description('Public URL of the app (the host name changes whenever the environment is recreated)')
output appUrl string = 'https://${appName}.${env.properties.defaultDomain}'
