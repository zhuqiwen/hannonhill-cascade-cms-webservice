<?php

declare(strict_types = 1);

namespace Edu\IU\Wcms\WebService;

class WCMSClient
{
    protected \SoapClient $client;
    protected array $authentication;
    protected string $site_name;
    protected string $wsdl;


    public function __construct(
        string $wsdl_url,
        string $site_name,
        array | null $soapRequestOptions = null
    ) {
        $this->site_name = trim($site_name);
        $this->authentication = [];
        $this->createWebServicesClient($wsdl_url, $soapRequestOptions);
        $this->wsdl = $wsdl_url;
    }


    public function getClient()
    {
        return $this->client;
    }

    public function getWSDL()
    {
        return $this->wsdl;
    }

    public function setAuthByKey(string $api_key): self
    {
        $this->authentication = [
            'apiKey' => trim($api_key)
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

    private function createWebServicesClient($wsdl_url, array | null $options = null): void
    {
        if(is_null($options)){
            $options = ['trace' => 1];
        }
        $this->client = new \SoapClient($wsdl_url, $options);
    }

    public function getSiteName(): string
    {
        return $this->site_name;
    }

    public function setSiteName(string $siteName)
    {
        $this->site_name = $siteName;
    }

    public function createAsset(string $type, \stdClass $asset): \stdClass
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


    /**
     * @param string $fromPath source asset path
     * @param string $toContainerPath target container path
     * @param string $sourceAssetType source asset type
     * @param string $toSiteName target site name
     * @param string $newAssetName  target asset name
     * @param bool $doWorkflow
     * @return void
     */
    public function copyAsset(string $fromPath, string $toContainerPath, string $sourceAssetType, string $toSiteName = '', string $newAssetName = '', bool $doWorkflow = false): void
    {
        $oldAssetName = explode('/', $fromPath);
        $oldAssetName = end($oldAssetName);
        $sourceIdentifier = [
            'type' => $sourceAssetType,
            'path' => [
                'path' => $fromPath,
                'siteName' => $this->site_name
            ]
        ];
        $targetContainerIdentifier = [
            'type' => $this->constructContainerType($sourceAssetType),
            'path' => [
                'path' => $toContainerPath,
                'siteName' => empty($toSiteName) ? $this->site_name : $toSiteName
            ]
        ];

        $copyParameters = [
            'destinationContainerIdentifier' => $targetContainerIdentifier,
            'doWorkflow' => $doWorkflow,
            'newName' => empty($newAssetName) ? $oldAssetName : $newAssetName
        ];

        $copy_options = [
            'authentication' => $this->authentication,
            'identifier' => $sourceIdentifier,
            'copyParameters' => $copyParameters
        ];

        $result = $this->client->copy($copy_options);

        if ($result->copyReturn->success !== 'true') {
            throw new \RuntimeException($result->copyReturn->message);
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

    private function constructContainerType(string $type): string{
        $folderedTypes = [
            'page',
            'file',
            'folder',
            'format',
            'symlink',
            'template',
            'block',
        ];
        $containeredTypes = [
            'metadataset',
            'pageconfigurationset',
            'datadefinition',
            'sharedfield',
            'contenttype',
            'assetfactory',
        ];
        $containers = [
            'metadatasetcontainer',
            'pageconfigurationsetcontainer',
            'datadefinitioncontainer',
            'sharedfieldcontainer',
            'contenttypecontainer',
            'assetfactorycontainer',
        ];

        if (in_array($type, $folderedTypes)) {
            return 'folder';
        }elseif (in_array($type, $containeredTypes)) {
            return $type.'container';
        }elseif (in_array($type, $containers)) {
            return $type;
        }else{
            throw new \RuntimeException("$type's container type is not supported yet.");
        }
    }

    /**
     *
     * construct identifier array
     * @param string $path
     * @param string $type
     * @param string $siteName
     * @return array
     */
    private function constructIdentifier(string $path, string $type, string $siteName = ''): array
    {
        return  [
            'type' => $type,
            'path' => [
                'path' => $path,
                'siteName' => $siteName === '' ? $this->site_name : $siteName
            ]
        ];
    }



    /**
     * ACCESS operations
     */

    public function readAccess(string $path, string $type):\stdClass
    {
        $options = [
            'authentication' => $this->authentication,
            'identifier' => $this->constructIdentifier($path, $type)
        ];

        $result = $this->client->readAccessRights($options);

        if ($result->readAccessRightsReturn->success === 'true') {
            return $result->readAccessRightsReturn->accessRightsInformation;
        } else {
            throw new \RuntimeException($result->readAccessRightsReturn->message);
        }
    }

    public function saveAccess(array $identifier, array $aclEntries, string $allLevel, bool $applyToChildren = false):void
    {
        // check necessary entries: identifier and allLevel are required, where aclEntries is optional
        $this->validateIdentifier($identifier);
        $this->validateAllLevel($allLevel);
        $this->validateAclEntries($aclEntries);

        $options = [
            'authentication' => $this->authentication,
            'accessRightsInformation' => [
                'identifier' => $identifier,
                'aclEntries' => [
                    'aclEntry' => $aclEntries
                ],
                'allLevel' => $allLevel,
            ],
            'applyToChildren' => $applyToChildren
        ];

        $result = $this->client->editAccessRights($options);

        if ($result->editAccessRightsReturn->success != 'true') {
            throw new \RuntimeException($result->editAccessRightsReturn->message);
        }

    }


    private function validateIdentifier(array $identifier):void
    {
        if (!isset($identifier['type'])) {
            throw new \RuntimeException("identifier type is not set.");
        }else{
            //TODO: manually parse WSDL for available string values of entityTypeString

        }
    }

    private function validateAllLevel(string $allLevel):void
    {

        if (!in_array($allLevel, ['none', 'read', 'write'])){
            $msg = "allLevel value not supported. It must be one of 'none', 'read', or 'write'. ";
            $msg .= $allLevel . ' is provided.';
            throw new \RuntimeException($msg);
        }


    }

    private function validateAclEntries(array $aclEntries):void
    {
        foreach ($aclEntries as $entry) {
            if (!in_array($entry['level'], ['write', 'read'])){
                $msg = "aclEntry level value not supported. It must be one of 'write', 'read'. " . $entry['level'] . ' is provided.';
                throw new \RuntimeException($msg);
            }

            if (!in_array($entry['type'], ['user', 'group'])){
                $msg = "aclEntry type value not supported. It must be one of 'user', 'group'. " . $entry['type'] . ' is provided.';
                throw new \RuntimeException($msg);
            }

            //TODO: check $entry['name']

        }
    }

}