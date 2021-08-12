<?php

declare(strict_types = 1);

namespace Edu\Iu\Vpcm\DC\Wcms\WebService;

class WCMSClient
{
    private $client;
    private $authentication;
    private $site_name;


    public function __construct(
        string $wsdl_url,
        string $site_name
    ) {
        $this->site_name = $site_name;
        $this->authentication = [];
        $this->createWebServicesClient($wsdl_url);
    }


    public function setAuthByKey(string $api_key): self
    {
        $this->authentication = [
            'apiKey' => $api_key
        ];

        return $this;
    }

    public function setAuthByUsernamePassword(string $username, string $password): self
    {
        $this->authentication = [
            'username' => $username,
            'password' => $password
        ];

        return $this;
    }

    private function createWebServicesClient($wsdl_url): void
    {
        $this->client = new \SoapClient($wsdl_url, ['trace' => 1]);
    }

    public function getSiteName(): string
    {
        return $this->site_name;
    }

    public function createAsset(string $type, \stdClass $asset): stdClass
    {
        $asset->siteName = $this->site_name;

        $create_options = [
            'authentication' => $this->authentication,
            'asset' => [$type => $asset]
        ];

        $result = $this->client->create($create_options);

        if ($result->createReturn->success !== 'true') {
            throw new \RuntimeException($result->createReturn->message);
        }

        return $result;
    }

    public function assetExists(string $path, string $type)
    {
        try {
            $asset = $this->fetchAsset($path, $type);

            return true;
        } catch (\Throwable $error) {
            if (stristr($error->getMessage(), "NO_SUCH_ASSET_MSG")) {
                return false;
            } else {

                // Returning null here because it's undetermined whether the requested
                // asset actually doesn't exist or if another issue with Web Services
                // prevented the client from reading the requested asset.

                return null;
            }
        }
    }

    public function fetchAsset(string $path, string $type): \stdClass
    {
        $identifier = [
            'type' => $type,
            'path' => [
                'path' => $path,
                'siteName' => $this->site_name
            ]
        ];

        $read_options = [
            'authentication' => $this->authentication,
            'identifier' => $identifier
        ];

        $result = $this->client->read($read_options);

        if ($result->readReturn->success === 'true') {
            return $result->readReturn->asset;
        } else {
            throw new \RuntimeException($result->readReturn->message);
        }
    }

    public function saveAsset(\stdClass $asset, string $type): void
    {
        $asset->siteName = $this->site_name;

        $edit_options = [
            'authentication' => $this->authentication,
            'asset' => [ $type => $asset ]
        ];

        $result = $this->client->edit($edit_options);

        if ($result->editReturn->success != 'true') {
            throw new \RuntimeException($result->editReturn->message);
        }
    }

    public function deleteAsset(string $type, string $path): void
    {
        $delete_options = [
            'authentication' => $this->authentication,
            'identifier' => [
                'path' => [
                    'path' => $path,
                    'siteName' => $this->site_name
                ],
                'type' => $type
            ]
        ];

        $result = $this->client->delete($delete_options);

        if ($result->deleteReturn->success != 'true') {
            throw new \RuntimeException($result->deleteReturn->message);
        }
    }

    public function readWorkflowSettings(string $type, string $path): \stdClass
    {
        $options = [
            'authentication' => $this->authentication,
            'identifier' => [
                'path' => [
                    'path' => $path,
                    'siteName' => $this->site_name
                ],
                'type' => $type
            ]
        ];

        $result = $this->client->readWorkflowSettings($options);


        if ($result->readWorkflowSettingsReturn->success === 'true') {
            return $result->readWorkflowSettingsReturn->workflowSettings;
        } else {
            throw new \RuntimeException($result->readWorkflowSettingsReturn->message);
        }
    }

    public function editWorkflowSettings(\stdClass $workflowSettings): void
    {
        $options = [
            'authentication' => $this->authentication,
            'workflowSettings' => $workflowSettings
        ];

        $result = $this->client->editWorkflowSettings($options);

        if ($result->editWorkflowSettingsReturn->success != 'true') {
            throw new \RuntimeException($result->editWorkflowSettingsReturn->message);
        }

    }

    public function search(\stdClass $searchInformation)
    {
        $options = [
            'authentication' => $this->authentication,
            'searchInformation' => $searchInformation
        ];

        $result = $this->client->search($options);

        if ($result->searchReturn->success === 'true') {
            return $result->searchReturn->matches;
        } else {
            throw new \RuntimeException($result->searchReturn->message);
        }
    }

    public function listSubscribersOfMetadataSet(string $path, string $siteName = "")
    {
        if($siteName === "")
        {
            $siteName = $this->site_name;
        }

        $options = [
            'authentication' => $this->authentication,
            'identifier' => (object) [
                "path" => [
                    "path" => $path,
                    "siteName" => $siteName
                ],
                "type" => "metadataset"
            ],
        ];

        $result = $this->client->listSubscribers($options);


        if($result->listSubscribersReturn->success === 'true')
        {
            // normalize what to return
            $results = [];
            $subscribers = (array)$result->listSubscribersReturn->subscribers;

            if(!empty($subscribers))
            {
                $subscribers = $subscribers['assetIdentifier'];
                if(is_array($subscribers))
                {
                    $results = $subscribers;
                }
                else
                {
                    $results = [$subscribers];
                }
            }

            return $results;
        }
        else
        {
            throw new \RuntimeException($result->listSubscribersReturn->message);
        }

    }

    public function listSubscribers(string $path, string $type, string $siteName = "")
    {
        if($siteName === "")
        {
            $siteName = $this->site_name;
        }

        $options = [
            'authentication' => $this->authentication,
            'identifier' => (object) [
                "path" => [
                    "path" => $path,
                    "siteName" => $siteName
                ],
                "type" => $type
            ],
        ];

        $result = $this->client->listSubscribers($options);


        if($result->listSubscribersReturn->success === 'true')
        {
            // normalize what to return
            $results = [];
            $subscribers = (array)$result->listSubscribersReturn->subscribers;

            if(!empty($subscribers))
            {
                $subscribers = $subscribers['assetIdentifier'];
                if(is_array($subscribers))
                {
                    $results = $subscribers;
                }
                else
                {
                    $results = [$subscribers];
                }
            }

            return $results;
        }
        else
        {
            throw new \RuntimeException($result->listSubscribersReturn->message);
        }

    }

    public function batchRead(array $reads = [])
    {
        $operations = [];
        foreach ($reads as $read)
        {
            $operations[] = [
                'read' => [
                    'authentication' => $this->authentication,
                    'identifier' => [
                        'type' => $read['type'],
                        'path' => [
                            'path' => $read['path'],
                            'siteName' => $this->site_name
                        ]
                    ]
                ],
            ];
        }

        $options = [
            'authentication' => $this->authentication,
            'operation' => $operations
        ];

        try
        {
            $result = $this->client->batch($options);

            // normalize what to return
            $results = [];
            if(is_array($result->batchReturn))
            {
                foreach ($result->batchReturn as $item)
                {
                    $results[] = $item->readResult;
                }
            }
            else
            {
                $results[] = $result->batchReturn->readResult;
            }

            return $results;

        }
        catch (\Exception $e)
        {
            throw new \RuntimeException($e->getMessage());
        }


    }

}