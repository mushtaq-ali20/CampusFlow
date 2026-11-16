using 'main.bicep'

// Secrets are not stored here. Pass them on the command line: -p dbPassword=... appKey=...
param dbPassword = readEnvironmentVariable('CF_DB_PASSWORD', '')
param appKey = readEnvironmentVariable('CF_APP_KEY', '')

param acrName = 'campusflowacr'
param mysqlServerName = 'campusflow-db'

// Phase 2 only: set these once the images are in the registry.
param appImage = readEnvironmentVariable('CF_APP_IMAGE', '')
param seedImage = readEnvironmentVariable('CF_SEED_IMAGE', '')
