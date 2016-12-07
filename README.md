# Sitewards Store Structure

This is a little extension for Magento 2 which allows to create the store-structure defined via configuration file.
Configuration file is expected to be in YAML format.

## Usage 

Install the extension normally, after that you should be able to 

`php bin/magento sitewards:store-structure:setup /path/to/configuration/store-structure.yaml`

see `sample-store-structure.yaml` in this repo for an example configuration.

## Known issues

1. It looks like some relationships might not be created by the way how extension creates websites.
   Consider the tool as for testing purposes only.
2. The cleanup option might cause data loss - handle with care and do backups ;)
3. Extension is not working with Symphony output interface - it is something to be improved later on. 
