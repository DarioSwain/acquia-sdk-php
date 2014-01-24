<?php

namespace Acquia\Test\Cloud\Api;

use Acquia\Cloud\Api\CloudApiClient;
use Acquia\Cloud\Api\CloudApiAuthPlugin;

class CloudApiClientTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var \Acquia\Test\Cloud\Api\CloudApiRequestListener
     */
    protected $requestListener;

    /**
     * @param string|null $responseFile
     *
     * @return \Acquia\Cloud\Api\CloudApiClient
     */
    public function getCloudApiClient($responseFile = null)
    {
        $cloudapi = CloudApiClient::factory(array(
            'base_url' => 'https://cloudapi.example.com',
            'username' => 'test-username',
            'password' => 'test-password',
        ));

        $this->requestListener = new CloudApiRequestListener();
        $cloudapi->getEventDispatcher()->addSubscriber($this->requestListener);

        if ($responseFile !== null) {
            $this->addMockResponse($cloudapi, $responseFile);
        }

        return $cloudapi;
    }

    /**
     * @param \Acquia\Cloud\Api\CloudApiClient $cloudapi
     * @param string $responseFile
     */
    public function addMockResponse(CloudApiClient $cloudapi, $responseFile)
    {
        $mock = new \Guzzle\Plugin\Mock\MockPlugin();

        $response = new \Guzzle\Http\Message\Response(200);
        if (is_string($responseFile)) {
            $response->setBody(file_get_contents($responseFile));
        }

        $mock->addResponse($response);
        $cloudapi->addSubscriber($mock);
    }

    /**
     * Helper function that returns the CloudApiAuthPlugin listener.
     *
     * @param \Acquia\Cloud\Api\CloudApiClient $cloudapi
     *
     * @return \Acquia\Cloud\Api\CloudApiAuthPlugin
     *
     * @throws \UnexpectedValueException
     */
    public function getRegisteredAuthPlugin(CloudApiClient $cloudapi)
    {
        $listeners = $cloudapi->getEventDispatcher()->getListeners('request.before_send');
        foreach ($listeners as $listener) {
            if (isset($listener[0]) && $listener[0] instanceof CloudApiAuthPlugin) {
                return $listener[0];
            }
        }

        throw new \UnexpectedValueException('Expecting subscriber Acquia\Cloud\Api\CloudApiAuthPlugin to be registered');
    }

    public function testGetBuilderParams()
    {
        $expected = array (
            'base_url' => 'https://cloudapi.example.com',
            'username' => 'test-username',
            'password' => 'test-password',
        );

        $cloudapi = $this->getCloudApiClient();
        $this->assertEquals($expected, $cloudapi->getBuilderParams());
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testRequireUsername()
    {
        CloudApiClient::factory(array(
            'password' => 'test-password',
        ));
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testRequirePassword()
    {
        CloudApiClient::factory(array(
            'username' => 'test-username',
        ));
    }

    public function testGetBasePath()
    {
        $cloudapi = $this->getCloudApiClient();
        $this->assertEquals('/v1', $cloudapi->getConfig('base_path'));
    }

    public function testHasAuthPlugin()
    {
        $cloudapi = $this->getCloudApiClient();
        $hasPlugin = (boolean) $this->getRegisteredAuthPlugin($cloudapi);
        return $this->assertTrue($hasPlugin);
    }

    public function testGetResponseBody()
    {
        $cloudapi = $this->getCloudApiClient(__DIR__ . '/json/sites.json');
        $response = $cloudapi->sites();
        $this->assertEquals(file_get_contents(__DIR__ . '/json/sites.json'), (string) $response);
    }

    public function testCallSites()
    {
        $cloudapi = $this->getCloudApiClient(__DIR__ . '/json/sites.json');
        $response = $cloudapi->sites();

        $expectedResponse = array(
            'stage-one:mysite',
            'stage-two:anothersite',
        );

        $this->assertEquals('https://cloudapi.example.com/v1/sites.json', $this->requestListener->getUrl());
        $this->assertInstanceOf('\Acquia\Cloud\Api\Response\Sites', $response);
        $this->assertEquals($expectedResponse, (array) $response);
    }

    public function testCallSite()
    {
        $cloudapi = $this->getCloudApiClient(__DIR__ . '/json/site.json');
        $response = $cloudapi->site('stage-one:mysite');

        $this->assertEquals('https://cloudapi.example.com/v1/sites/stage-one%3Amysite.json', $this->requestListener->getUrl());
        $this->assertInstanceOf('\Acquia\Cloud\Api\Response\Site', $response);
        $this->assertEquals('stage-one:mysite', (string) $response);

        $this->assertEquals('stage-one:mysite', $response->name());
        $this->assertFalse($response->productionMode());
        $this->assertEquals('My Site', $response->title());
        $this->assertEquals('mysite', $response->unixUsername());
        $this->assertEquals('8067383e-fde3-102e-8305-1231390f2cc1', $response->uuid());
        $this->assertEquals('git', $response->vcsType());
        $this->assertEquals('mysite@svn-1.stage-one.hosting.acquia.com:mysite.git', $response->vcsUrl());
    }

    public function testCallEnvironments()
    {
        $cloudapi = $this->getCloudApiClient(__DIR__ . '/json/environments.json');
        $response = $cloudapi->environments('stage-one:mysite');

        $this->assertEquals('https://cloudapi.example.com/v1/sites/stage-one%3Amysite/envs.json', $this->requestListener->getUrl());
        $this->assertInstanceOf('\Acquia\Cloud\Api\Response\Environments', $response);
        foreach ($response as $object) {
            $this->assertInstanceOf('\Acquia\Cloud\Api\Response\Environment', $object);
        }
    }

    public function testCallEnvironment()
    {
        $cloudapi = $this->getCloudApiClient(__DIR__ . '/json/environment.json');
        $response = $cloudapi->environment('stage-one:mysite', 'prod');

        $this->assertEquals('https://cloudapi.example.com/v1/sites/stage-one%3Amysite/envs/prod.json', $this->requestListener->getUrl());
        $this->assertInstanceOf('\Acquia\Cloud\Api\Response\Environment', $response);
        $this->assertEquals('prod', (string) $response);

        $this->assertEquals(array('456'), $response->dbClusters());
        $this->assertEquals('mysite.stage-one.acquia-sites.com', $response->defaultDomain());
        $this->assertEquals('ded-456.stage-one.acquia-sites.com', $response->sshHost());
        $this->assertEquals('tags/WELCOME', $response->vcsPath());
        $this->assertFalse($response->liveDev());
        $this->assertEquals('prod', $response->name());
    }

    public function testCallServers()
    {
        $cloudapi = $this->getCloudApiClient(__DIR__ . '/json/servers.json');
        $response = $cloudapi->servers('stage-one:mysite', 'prod');

        $this->assertEquals('https://cloudapi.example.com/v1/sites/stage-one%3Amysite/envs/prod/servers.json', $this->requestListener->getUrl());
        $this->assertInstanceOf('\Acquia\Cloud\Api\Response\Servers', $response);
        foreach ($response as $object) {
            $this->assertInstanceOf('\Acquia\Cloud\Api\Response\Server', $object);
        }
    }

    public function testCallServer()
    {
        $cloudapi = $this->getCloudApiClient(__DIR__ . '/json/server.json');
        $response = $cloudapi->server('stage-one:mysite', 'prod', 'ded-123');

        $this->assertEquals('https://cloudapi.example.com/v1/sites/stage-one%3Amysite/envs/prod/servers/ded-123.json', $this->requestListener->getUrl());
        $this->assertInstanceOf('\Acquia\Cloud\Api\Response\Server', $response);
        $this->assertEquals('bal-751', (string) $response);

        $this->assertEquals('us-east-1', $response->region());
        $this->assertEquals('m1.large', $response->amiType());
        $this->assertEquals('us-east-1c', $response->availabilityZone());
        $this->assertEquals('bal-751', $response->name());
        $this->assertEquals('bal-751.prod.hosting.acquia.com', $response->fqdn());

        $expectedServices = array (
            'varnish' =>
            array (
                'status' => 'active',
            ),
            'external_ip' => '50.19.98.136',
        );
        $this->assertEquals($expectedServices, $response->services());
    }

    public function testCallSshKeys()
    {
        $cloudapi = $this->getCloudApiClient(__DIR__ . '/json/ssh_keys.json');
        $response = $cloudapi->sshKeys('stage-one:mysite');

        $this->assertEquals('https://cloudapi.example.com/v1/sites/stage-one%3Amysite/sshkeys.json', $this->requestListener->getUrl());
        $this->assertInstanceOf('\Acquia\Cloud\Api\Response\SshKeys', $response);
        foreach ($response as $object) {
            $this->assertInstanceOf('\Acquia\Cloud\Api\Response\SshKey', $object);
        }
    }

    public function testCallSshKey()
    {
        $cloudapi = $this->getCloudApiClient(__DIR__ . '/json/ssh_key.json');
        $response = $cloudapi->sshKey('stage-one:mysite', '12345');

        $this->assertEquals('https://cloudapi.example.com/v1/sites/stage-one%3Amysite/sshkeys/12345.json', $this->requestListener->getUrl());
        $this->assertInstanceOf('\Acquia\Cloud\Api\Response\SshKey', $response);
        $this->assertEquals('12345', (string) $response);

        $this->assertEquals('12345', $response->id());
        $this->assertEquals('ssh-rsa AAAA== test@example.com', $response->publicKey());
        $this->assertEquals('test@example.com', $response->nickname());
    }

    public function testCallAddSshKey()
    {
        $cloudapi = $this->getCloudApiClient(__DIR__ . '/json/ssh_key_add.json');
        $response = $cloudapi->addSshKey('stage-one:mysite', 'ssh-rsa AAAA== test@example.com', 'test@example.com');

        $this->assertEquals('https://cloudapi.example.com/v1/sites/stage-one%3Amysite/sshkeys.json?nickname=test%40example.com', $this->requestListener->getUrl());
        $this->assertInstanceOf('\Acquia\Cloud\Api\Response\Task', $response);
        $this->assertEquals('12345', (string) $response);

        $this->assertEquals('12345', $response->id());
        $this->assertEquals('waiting', $response->state());
        $this->assertFalse($response->started());
        $this->assertArrayHasKey('sitegroup', $response->body());
        $this->assertFalse($response->hidden());
        $this->assertEquals('Update SSH key AAAA==', $response->description());
        $this->assertNull($response->result());
        $this->assertFalse($response->completed());
        $this->assertInstanceOf('\DateTime', $response->created());
        $this->assertEquals('site-update', $response->queue());
        $this->assertArrayHasKey('action', $response->cookie());
        $this->assertNull($response->recipient());
        $this->assertEquals('SiteUpdateFactory', $response->sender());
        $this->assertNull($response->percentage());
    }

    public function testCallDeleteSshKey()
    {
        $cloudapi = $this->getCloudApiClient(__DIR__ . '/json/ssh_key_add.json');
        $response = $cloudapi->deleteSshKey('stage-one:mysite', '12345');

        $this->assertEquals('https://cloudapi.example.com/v1/sites/stage-one%3Amysite/sshkeys/12345.json', $this->requestListener->getUrl());
        $this->assertInstanceOf('\Acquia\Cloud\Api\Response\Task', $response);
        $this->assertEquals('12345', (string) $response);

        $this->assertEquals('12345', $response->id());
        $this->assertEquals('waiting', $response->state());
        $this->assertFalse($response->started());
        $this->assertArrayHasKey('sitegroup', $response->body());
        $this->assertFalse($response->hidden());
        $this->assertEquals('Update SSH key AAAA==', $response->description());
        $this->assertNull($response->result());
        $this->assertFalse($response->completed());
        $this->assertInstanceOf('\DateTime', $response->created());
        $this->assertEquals('site-update', $response->queue());
        $this->assertArrayHasKey('action', $response->cookie());
        $this->assertNull($response->recipient());
        $this->assertEquals('SiteUpdateFactory', $response->sender());
        $this->assertNull($response->percentage());
    }

    public function testCallSiteDatabases()
    {
        $cloudapi = $this->getCloudApiClient(__DIR__ . '/json/site_databases.json');
        $response = $cloudapi->siteDatabases('stage-one:mysite');

        $this->assertEquals('https://cloudapi.example.com/v1/sites/stage-one%3Amysite/dbs.json', $this->requestListener->getUrl());
        $this->assertInstanceOf('\Acquia\Cloud\Api\Response\DatabaseNames', $response);
        foreach ($response as $object) {
            $this->assertInstanceOf('\Acquia\Cloud\Api\Response\DatabaseName', $object);
        }
    }

    public function testCallSiteDatabase()
    {
        $cloudapi = $this->getCloudApiClient(__DIR__ . '/json/site_database.json');
        $response = $cloudapi->siteDatabase('stage-one:mysite', 'mysite');

        $this->assertEquals('https://cloudapi.example.com/v1/sites/stage-one%3Amysite/dbs/mysite.json', $this->requestListener->getUrl());
        $this->assertInstanceOf('\Acquia\Cloud\Api\Response\DatabaseName', $response);
        $this->assertEquals('mysite', (string) $response);

        $this->assertEquals('mysite', $response->name());
    }

    public function testCallEnvironmentDatabases()
    {
        $cloudapi = $this->getCloudApiClient(__DIR__ . '/json/env_databases.json');
        $response = $cloudapi->environmentDatabases('stage-one:mysite', 'prod');

        $this->assertEquals('https://cloudapi.example.com/v1/sites/stage-one%3Amysite/envs/prod/dbs.json', $this->requestListener->getUrl());
        $this->assertInstanceOf('\Acquia\Cloud\Api\Response\Databases', $response);
        foreach ($response as $object) {
            $this->assertInstanceOf('\Acquia\Cloud\Api\Response\Database', $object);
        }
    }

    public function testCallEnvironmentDatabase()
    {
        $cloudapi = $this->getCloudApiClient(__DIR__ . '/json/env_database.json');
        $response = $cloudapi->environmentDatabase('stage-one:mysite', 'prod', 'mysite');

        $this->assertEquals('https://cloudapi.example.com/v1/sites/stage-one%3Amysite/envs/prod/dbs/mysite.json', $this->requestListener->getUrl());
        $this->assertInstanceOf('\Acquia\Cloud\Api\Response\Database', $response);
        $this->assertEquals('mysite', (string) $response);

        $this->assertEquals('mysite', $response->name());
        $this->assertEquals('mysite', $response->username());
        $this->assertEquals('mysite', $response->instanceName());
        $this->assertEquals('abcd1234', $response->password());
        $this->assertEquals('123', $response->dbCluster());
        $this->assertEquals('ded-123', $response->host());
    }

    public function testCallDatabaseBackups()
    {
        $cloudapi = $this->getCloudApiClient(__DIR__ . '/json/database_backups.json');
        $response = $cloudapi->databaseBackups('stage-one:mysite', 'prod', 'mysite');

        $this->assertEquals('https://cloudapi.example.com/v1/sites/stage-one%3Amysite/envs/prod/dbs/mysite/backups.json', $this->requestListener->getUrl());
        $this->assertInstanceOf('\Acquia\Cloud\Api\Response\DatabaseBackups', $response);
        foreach ($response as $object) {
            $this->assertInstanceOf('\Acquia\Cloud\Api\Response\DatabaseBackup', $object);
        }
    }

    public function testCallDatabaseBackup()
    {
        $cloudapi = $this->getCloudApiClient(__DIR__ . '/json/database_backup.json');
        $response = $cloudapi->databaseBackup('stage-one:mysite', 'prod', 'mysite', '12345');

        $this->assertEquals('https://cloudapi.example.com/v1/sites/stage-one%3Amysite/envs/prod/dbs/mysite/backups/12345.json', $this->requestListener->getUrl());
        $this->assertInstanceOf('\Acquia\Cloud\Api\Response\DatabaseBackup', $response);
        $this->assertEquals('12345', (string) $response);

        $this->assertEquals('12345', $response->id());
        $this->assertEquals('497dd0b132fd160d4aef810d2a24f9e1', $response->checksum());
        $this->assertEquals('mysite', $response->databaseName());
        $this->assertFalse($response->deleted());
        $this->assertEquals(0, strpos($response->link(), 'http://mysite.stage-one'));
        $this->assertInstanceOf('\DateTime', $response->started());
        $this->assertEquals('daily', $response->type());
        $this->assertInstanceOf('\DateTime', $response->completed());
        $this->assertEquals('backups/prod-mysite-mysite-2014-01-21.sql.gz', $response->path());
    }

    public function testCallDeleteDatabaseBackup()
    {
        $cloudapi = $this->getCloudApiClient(__DIR__ . '/json/database_backup_delete.json');
        $response = $cloudapi->deleteDatabaseBackup('stage-one:mysite', 'prod', 'mysite', '12345');

        $this->assertEquals('https://cloudapi.example.com/v1/sites/stage-one%3Amysite/envs/prod/dbs/mysite/backups/12345.json', $this->requestListener->getUrl());
        $this->assertInstanceOf('\Acquia\Cloud\Api\Response\Task', $response);
        $this->assertEquals('12345', (string) $response);

        $this->assertEquals('12345', $response->id());
        $this->assertEquals('received', $response->state());
        $this->assertFalse($response->started());
        $this->assertEquals(array('12345'), $response->body());
        $this->assertFalse($response->hidden());
        $this->assertEquals('Delete backup 12345 of database mysite in prod environment.', $response->description());
        $this->assertNull($response->result());
        $this->assertFalse($response->completed());
        $this->assertInstanceOf('\DateTime', $response->created());
        $this->assertEquals('delete-db-backup', $response->queue());
        $this->assertNull($response->cookie());
        $this->assertEquals('backup-123.stage-one.hosting.acquia.com', $response->recipient());
        $this->assertEquals('cloud_api', $response->sender());
        $this->assertNull($response->percentage());
    }

    public function testCallDownloadDatabaseBackup()
    {
        $cloudapi = $this->getCloudApiClient(__DIR__ . '/json/database_backup_download.txt');
        $response = $cloudapi->downloadDatabaseBackup('stage-one:mysite', 'prod', 'mysite', '12345', './build/test/database_backup_download.txt');

        $this->assertInstanceOf('\Guzzle\Http\Message\Response', $response);
        $this->assertEquals("test\n", file_get_contents('./build/test/database_backup_download.txt'));

        @unlink('./build/test/database_backup_download.txt');
    }

    public function testCallCreateDatabaseBackup()
    {
        $cloudapi = $this->getCloudApiClient(__DIR__ . '/json/database_backup_create.json');
        $response = $cloudapi->createDatabaseBackup('stage-one:mysite', 'mysite', 'prod', '12345');

        $this->assertEquals('https://cloudapi.example.com/v1/sites/stage-one%3Amysite/envs/mysite/dbs/prod/backups.json', $this->requestListener->getUrl());
        $this->assertInstanceOf('\Acquia\Cloud\Api\Response\Task', $response);
        $this->assertEquals('12345', (string) $response);

        $this->assertEquals('12345', $response->id());
        $this->assertEquals('waiting', $response->state());
        $this->assertFalse($response->started());
        $this->assertEquals(array('mysite', 'prod', 'mysite'), $response->body());
        $this->assertFalse($response->hidden());
        $this->assertEquals('Backup database mysite in prod environment.', $response->description());
        $this->assertNull($response->result());
        $this->assertFalse($response->completed());
        $this->assertInstanceOf('\DateTime', $response->created());
        $this->assertEquals('create-db-backup-ondemand', $response->queue());
        $this->assertNull($response->cookie());
        $this->assertNull($response->recipient());
        $this->assertEquals('cloud_api', $response->sender());
        $this->assertNull($response->percentage());
    }

    public function testCallTasks()
    {
        $cloudapi = $this->getCloudApiClient(__DIR__ . '/json/tasks.json');
        $response = $cloudapi->tasks('stage-one:mysite');

        $this->assertEquals('https://cloudapi.example.com/v1/sites/stage-one%3Amysite/tasks.json', $this->requestListener->getUrl());
        $this->assertInstanceOf('\Acquia\Cloud\Api\Response\Tasks', $response);

        foreach ($response as $object) {
            $this->assertInstanceOf('\Acquia\Cloud\Api\Response\Task', $object);
        }
    }

    public function testCallTask()
    {
        $cloudapi = $this->getCloudApiClient(__DIR__ . '/json/task.json');
        $response = $cloudapi->task('stage-one:mysite', '12345');

        $this->assertEquals('https://cloudapi.example.com/v1/sites/stage-one%3Amysite/tasks/12345.json', $this->requestListener->getUrl());
        $this->assertInstanceOf('\Acquia\Cloud\Api\Response\Task', $response);

        // NOTE: Task methods are already well-tested so we don't have to do
        // anything else here.
    }

    /**
     * @deprecated since version 0.5.0
     */
    public function testCallTaskInfo()
    {
        $cloudapi = $this->getCloudApiClient(__DIR__ . '/json/task.json');
        $response = $cloudapi->taskInfo('stage-one:mysite', '12345');

        $this->assertEquals('https://cloudapi.example.com/v1/sites/stage-one%3Amysite/tasks/12345.json', $this->requestListener->getUrl());
        $this->assertInstanceOf('\Acquia\Cloud\Api\Response\Task', $response);
    }
}