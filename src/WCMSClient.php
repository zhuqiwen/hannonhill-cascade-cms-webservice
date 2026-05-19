<?php

declare(strict_types = 1);

namespace Edu\IU\Wcms\WebService;

class WCMSClient
{
    protected \SoapClient $client;
    protected array $authentication;
    protected string $site_name;
    protected string $wsdl;

    protected Batch $batch;
    protected Access $access;

    use WCMSClientTraits;


    /**
     * @throws \SoapFault
     */
    public function __construct(
        string $wsdl_url,
        string $site_name,
        array | null $soapRequestOptions = null,
        ?string $username = null,
        ?string $password = null,
        ?string $apiKey = null,
    ) {
        $this->site_name = trim($site_name);
        $this->authentication = [];
        $this->createWebServicesClient($wsdl_url, $soapRequestOptions);
        $this->wsdl = $wsdl_url;

        if (!$username || !$password) {
            $this->setAuthByUsernamePassword($username, $password);
        }

        if (!$apiKey) {
            $this->setAuthByKey($apiKey);
        }

        $this->batch = new Batch($this->client, $this->authentication, $this->site_name);
        $this->access = new Access($this->client, $this->authentication, $this->site_name);


    }


    public function getClient(): \SoapClient
    {
        return $this->client;
    }

    public function getWSDL(): string
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

    /**
     * @throws \SoapFault
     */
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

    public function setSiteName(string $siteName): void
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

    public function assetExists(string $path, string $type): ?bool
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
        $read_options = [
            'authentication' => $this->authentication,
            'identifier' => $this->constructIdentifier($path, $type)
        ];

        $result = $this->client->read($read_options);

        if ($result->readReturn->success === 'true') {
            return $result->readReturn->asset;
        } else {
            throw new \RuntimeException($result->readReturn->message);
        }
    }


    public function fetchAssetWithId(string $assetId, string $type):\stdClass
    {
        $options = [
            'authentication' => $this->authentication,
            'identifier' => $this->constructIdentifierWithId($assetId, $type)
        ];

        $result = $this->client->read($options);

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

        $sourceIdentifier = $this->constructIdentifier($fromPath, $sourceAssetType);

        $targetContainerIdentifier = $this->constructIdentifier(
            $toContainerPath,
            $this->constructContainerType($sourceAssetType),
            empty($toSiteName) ? $this->site_name : $toSiteName
        );

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



    public function copyAssetById(string $sourceId, string $toContainerPath, string $sourceAssetType, string $toSiteName = '', string $newAssetName = '', bool $doWorkflow = false): void
    {

        $sourceIdentifier = $this->constructIdentifierWithId($sourceId, $sourceAssetType);

        $targetContainerIdentifier = $this->constructIdentifier(
            $toContainerPath,
            $this->constructContainerType($sourceAssetType),
            empty($toSiteName) ? $this->site_name : $toSiteName
        );

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

    // rename only happens in same site
    public function renameAsset(string $path, string $type, string $newName, bool $doWorkflow = false): void
    {
        $moveParameters = [
            'doWorkflow' => $doWorkflow,
            'newName' => $newName,
        ];

        $move_options = [
            'authentication' => $this->authentication,
            'identifier' => $this->constructIdentifier($path, $type),
            'moveParameters' => $moveParameters,
        ];

        $resul = $this->client->move($move_options);
        if ($resul->moveReturn->success !== 'true') {
            throw new \RuntimeException($resul->moveReturn->message);
        }
        
    }

    // rename only happens in same site
    public function renameAssetById(string $id, string $type, string $newName, bool $doWorkflow = false): void
    {
        $moveParameters = [
            'doWorkflow' => $doWorkflow,
            'newName' => $newName,
        ];

        $move_options = [
            'authentication' => $this->authentication,
            'identifier' => $this->constructIdentifierWithId($id, $type),
            'moveParameters' => $moveParameters,
        ];

        $resul = $this->client->move($move_options);
        if ($resul->moveReturn->success !== 'true') {
            throw new \RuntimeException($resul->moveReturn->message);
        }

    }

    public function moveAsset(string $path, string $type, string $newParentPath, bool $doWorkflow = false):void
    {

        $moveParameters = $this->constructMoveParameters($newParentPath, $type, $doWorkflow);

        $move_options = [
            'authentication' => $this->authentication,
            'identifier' => $this->constructIdentifier($path, $type),
            'moveParameters' => $moveParameters,
        ];

        $resul = $this->client->move($move_options);
        if ($resul->moveReturn->success !== 'true') {
            throw new \RuntimeException($resul->moveReturn->message);
        }
    }

    public function moveAssetById(string $id, string $type, string $newParentPath, bool $doWorkflow = false): void
    {

        $moveParameters = [
            'doWorkflow' => $doWorkflow,
            'destinationContainerIdentifier' => $this->constructIdentifier($newParentPath, $this->getContainerType($type)),
        ];

        $move_options = [
            'authentication' => $this->authentication,
            'identifier' => $this->constructIdentifierWithId($id, $type),
            'moveParameters' => $moveParameters,
        ];

        $resul = $this->client->move($move_options);
        if ($resul->moveReturn->success !== 'true') {
            throw new \RuntimeException($resul->moveReturn->message);
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
            'identifier' => $this->constructIdentifier($path, $type),
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
            'identifier' => $this->constructIdentifier($path, $type),
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
            'identifier' => $this->constructIdentifier($path, 'metadataset', $siteName)
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
            'identifier' => $this->constructIdentifier($path, $type, $siteName)
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

    public function relationships(string $path, string $type, string $siteName = ""): array
    {
        if($siteName === "")
        {
            $siteName = $this->site_name;
        }

        $options = [
            'authentication' => $this->authentication,
            'identifier' => $this->constructIdentifier($path, $type, $siteName)
        ];

        $result = $this->client->relationships($options);


        if($result->lrelationshipsReturn->success === 'true')
        {
            // normalize what to return
            $results = [];
            $subscribers = (array)$result->lrelationshipsReturn->subscribers;

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


    /**
     * BATCH
     */

    /**
     * @param array $reads
     * @return array
     */
    public function batchRead(array $reads = []):array
    {

        return $this->batch->read($reads);
    }

    /**
     * @param array $moves
     * @return array
     */
    public function batchMove(array $moves = []):array
    {
        return $this->batch->move($moves);
    }

    public function batchCreate(array $creates = []):array
    {
        return $this->batch->create($creates);
    }

    public function batchDelete(array $deletes = []):array
    {
        return $this->batch->delete($deletes);
    }

    public function batchEdit(array $edits = []):array
    {
        return $this->batch->edit($edits);
    }

    public function batchPublish(array $publishes = []):array
    {
        return $this->batch->publish($publishes);
    }

    public function batchReadAccess(array $reads = []):array
    {
        return $this->batch->readAccessRights($reads);
    }

    public function batchEditAccessRights(array $edits = []):array
    {
        return $this->batch->editAccessRights($edits);
    }

    public function batchRelationship(array $relationships = []):array
    {
        return $this->batch->relationships($relationships);
    }

    public function batchCopy(array $copies = []):array
    {
        return $this->batch->copy($copies);
    }

    /**
     * END: BATCH
     */

    //TODO: add unpublish()


    public function publishAssetById(string $id, string $type, array $destinations = []):void
    {
        $publishInfo = [
            'identifier' => $this->constructIdentifierWithId($id, $type),
            'unpublish' => false,
            'destinations' => $destinations
        ];
        $publish_options = [
            'authentication' => $this->authentication,
            'publishInformation' => $publishInfo
        ];

        $result = $this->client->publish($publish_options);

        if ($result->publishReturn->success != 'true') {
            throw new \RuntimeException($result->publishReturn->message);
        }
    }

    public function publishAsset(string $path, string $type, array $destinations = []):void
    {
        $publishInfo = [
            'identifier' => $this->constructIdentifier($path, $type),
            'unpublish' => false,
            'destinations' => $destinations
        ];
        $publish_options = [
            'authentication' => $this->authentication,
            'publishInformation' => $publishInfo
        ];

        $result = $this->client->publish($publish_options);

        if ($result->publishReturn->success != 'true') {
            throw new \RuntimeException($result->publishReturn->message);
        }
    }


    public function unpublishAssetById(string $id, string $type, array $destinations = []):void
    {
        $publishInfo = [
            'identifier' => $this->constructIdentifierWithId($id, $type),
            'unpublish' => true,
            'destinations' => $destinations
        ];
        $publish_options = [
            'authentication' => $this->authentication,
            'publishInformation' => $publishInfo
        ];

        $result = $this->client->publish($publish_options);

        if ($result->publishReturn->success != 'true') {
            throw new \RuntimeException($result->publishReturn->message);
        }
    }

    public function unpublishAsset(string $path, string $type, array $destinations = []):void
    {
        $publishInfo = [
            'identifier' => $this->constructIdentifier($path, $type),
            'unpublish' => true,
            'destinations' => $destinations
        ];
        $publish_options = [
            'authentication' => $this->authentication,
            'publishInformation' => $publishInfo
        ];

        $result = $this->client->publish($publish_options);

        if ($result->publishReturn->success != 'true') {
            throw new \RuntimeException($result->publishReturn->message);
        }
    }


    public function addComment(string $comment, string $path, string $type)
    {
        $checkOutOptions = [
            'authentication' => $this->authentication,
            'identifier' => $this->constructIdentifier($path, $type),
        ];
        $result = $this->client->checkOut($checkOutOptions);
        if ($result->checkOutReturn->success != 'true') {
            throw new \RuntimeException($result->checkOutReturn->message);
        }

        $checkInOptions = [
            'authentication' => $this->authentication,
            'identifier' => $this->constructIdentifier($path, $type),
            'comments' => $comment,
        ];
        $result = $this->client->checkIn($checkInOptions);
        if ($result->checkInReturn->success != 'true') {
            throw new \RuntimeException($result->checkInReturn->message);
        }
    }

    public function addCommentById(string $comment, string $id, string $type):void
    {
        $checkOutOptions = [
            'authentication' => $this->authentication,
            'identifier' => $this->constructIdentifierWithId($id, $type),
        ];
        $result = $this->client->checkOut($checkOutOptions);
        if ($result->checkOutReturn->success != 'true') {
            throw new \RuntimeException($result->checkOutReturn->message);
        }

        $checkInOptions = [
            'authentication' => $this->authentication,
            'identifier' => $this->constructIdentifierWithId($id, $type),
            'comments' => $comment,
        ];
        $result = $this->client->checkIn($checkInOptions);
        if ($result->checkInReturn->success != 'true') {
            throw new \RuntimeException($result->checkInReturn->message);
        }
    }



    public function publishByDestinationPath(string $destinationPath, string $siteName = "")
    {

        $publishInfo = [
            'identifier' => $this->constructIdentifier($destinationPath, 'destination', $siteName),
            'unpublish' => false

        ];
        $publish_options = [
            'authentication' => $this->authentication,
            'publishInformation' => $publishInfo
        ];

        $result = $this->client->publish($publish_options);

        if ($result->publishReturn->success != 'true') {
            throw new \RuntimeException($result->publishReturn->message);
        }

    }

    public function publishByDestinationId(string $destinationId)
    {
        $publishInfo = [
            'identifier' => $this->constructIdentifierWithId($destinationId, 'destination'),
            'unpublish' => false
        ];
        $publish_options = [
            'authentication' => $this->authentication,
            'publishInformation' => $publishInfo
        ];

        $result = $this->client->publish($publish_options);

        if ($result->publishReturn->success != 'true') {
            throw new \RuntimeException($result->publishReturn->message);
        }

    }













    /**
     * ACCESS operations
     */

    public function readAccess(string $path, string $type):\stdClass
    {
        return $this->access->read($path, $type);
    }

    public function readAccessById(string $id, string $type):\stdClass
    {
        return $this->access->readById($id, $type);
    }


    public function saveAccessById(string $id, string $type, array $aclEntries, string $allLevel, bool $applyToChildren = false):void
    {
        // check necessary entries: identifier and allLevel are required, where aclEntries is optional
        $identifier = $this->constructIdentifierWithId($id, $type);
        $this->saveAccess($identifier, $aclEntries, $allLevel, $applyToChildren);

    }

    public function saveAccessByPath(string $path, string $type, array $aclEntries, string $allLevel, bool $applyToChildren = false, $siteName = ''):void
    {
        // check necessary entries: identifier and allLevel are required, where aclEntries is optional
        $identifier = $this->constructIdentifier($path, $type, $siteName);
        $this->saveAccess($identifier, $aclEntries, $allLevel, $applyToChildren);

    }

    protected function saveAccess(array $identifier, array $aclEntries, string $allLevel, bool $applyToChildren = false):void
    {
        // check necessary entries: identifier and allLevel are required, where aclEntries is optional
        $this->validateIdentifier($identifier);
        $this->validateAllLevel($allLevel);
        $this->validateAclEntries($aclEntries);

        $this->access->saveAccess($identifier, $aclEntries, $allLevel, $applyToChildren);

    }






}