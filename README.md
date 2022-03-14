# wr-openlineage

![Build status](https://github.com/keboola/wr-openlineage/actions/workflows/push.yml/badge.svg)

This component will push jobs metadata obtained from [Job Queue API open-api-lineage endpoint](https://app.swaggerhub.com/apis-docs/keboola/job-queue-api/1.2.6#/Jobs/getJobOpenApiLineage) into a data lineage service supporting OpenLineage API (e.g. Marquez).

# Usage

- Set up your OpenLineage API if you don't have one already e.g. Marquez (https://marquezproject.github.io/marquez/running-on-aws.html)
- In the component configuration, set `openlineage_api_url` to hostname of your API  
- Set `created_time_from` - all Keboola Connection jobs from this point will be imported into your OpenLineage API
- Configure SSH proxy if needed


## Development
 
Clone this repository and init the workspace with following command:

```
git clone https://github.com/keboola/wr-openlineage
cd wr-openlineage
docker-compose build
docker-compose run --rm dev composer install --no-scripts
```

Run the test suite using this command:

```
docker-compose run --rm dev composer tests
```
 
# Integration

For information about deployment and integration with KBC, please refer to the [deployment section of developers documentation](https://developers.keboola.com/extend/component/deployment/)
