<?php

declare(strict_types = 1);

namespace Edu\IU\Wcms\WebService;

class Batch extends WCMSClientOperationAbstract {

    private array $batchTypes = [
        'operation',
        'checkOut',
        'create',
        'listMessage',
        'read',
        'readAccessRights',
        'readWorkflowSettings',
        'readAudits',
        'listSubscribers',
        'relationships',
        'search',
        'readWorkflowInformation',
    ];

    public function doBatch(array $batchOptions, string $batchType = 'operation'):array
    {
        if (!in_array($batchType, $this->batchTypes)){
            throw new \RuntimeException("Batch type '$batchType' is not supported");
        }

        $resultType  = $batchType . 'Result';
        try
        {
            $result = $this->client->batch($batchOptions);

            // normalize what to return
            $results = [];
            if(is_array($result->batchReturn))
            {
                foreach ($result->batchReturn as $item)
                {
                    $results[] = $item->$resultType;
                }
            }
            else
            {
                $results[] = $result->batchReturn->$resultType;
            }

            return $results;

        }
        catch (\Exception $e)
        {
            throw new \RuntimeException($e->getMessage());
        }
    }

    protected function getBatchOptions(array $operations):array
    {
        return [
            'authentication' => $this->authentication,
            'operation' => $operations
        ];
    }



    /**
     * OPERATIONS
     */
    public function delete(array $deletes = []):array
    {
        $operations = [];
        foreach ($deletes as $delete){
            $path = $delete['path'];
            $type = $delete['type'];
            $operations[] = [$this->constructOperationDelete($path, $type)
            ];
        }

        return $this->doBatch($this->getBatchOptions($operations));
    }
    public function move(array $moves = []):array
    {
        $operations = [];
        $this->validateMoves($moves);

        foreach ($moves as $move) {

            $type = $move['type'];
            $path = $move["path"];
            $newParentPath = $move['newParentPath'];
            $doWorkflow = $move['doWorkflow'] ?? false;


            $operations[] = [
                $this->constructOperationMove($path, $type, $newParentPath, $doWorkflow)
            ];


        }

        return $this->doBatch($this->getBatchOptions($operations));
    }
    public function edit(array $edits = []):array
    {
        $operations = [];
        $this->validateEdits($edits);
        foreach ($edits as $edit){
            $type = $edit['type'];
            $asset = is_array($edit['asset']) ? $edit['asset'] : (object)$edit['asset'];
            $operations[] = [
                $this->constructOperationEdit($type, $asset)
            ];
        }

        return $this->doBatch($this->getBatchOptions($operations));
    }

    public function publish(array $publishes = []):array
    {
        $operations = [];
        //TODO: add validatePublishes()
        foreach ($publishes as $publish){
            $path = $publish['path'];
            $type = $publish['type'];
            $destinations = $publish['destinations'] ?? [];

            $operations[] = [
                'publish' => [
                    'authentication' => $this->authentication,
                    'publishInformation' => $this->constructPublishInformation($path, $type, $destinations)
                ]

            ];
        }

        return $this->doBatch($this->getBatchOptions($operations));
    }
    public function unpublish(array $unpublishes = []):array
    {
        $operations = [];
        //TODO: add validatePublishes()
        foreach ($unpublishes as $unpublish){
            $path = $unpublish['path'];
            $type = $unpublish['type'];
            $destinations = $unpublish['destinations'] ?? [];
            $operations[] = [
                'publish' => [
                    'authentication' => $this->authentication,
                    'publishInformation' => $this->constructPublishInformation($path, $type, $destinations, true)
                ]

            ];
        }

        return $this->doBatch($this->getBatchOptions($operations));
    }
    public function editAccessRights(array $editAccessRights = []):array
    {

        $operations = [];
        //TODO: add validateAccessRights()

        foreach ($editAccessRights as $editAccessRight){
            $path = $editAccessRight['path'];
            $type = $editAccessRight['type'];
            $aclEntries = $editAccessRight['aclEntries'] ?? [];
            $allLevel = $editAccessRight['allLevel'] ?? 'none';
            $applyToChildren = $editAccessRight['applyToChildren'] ?? false;

            $operations[] = [
                'editAccessRights' => [
                    'authentication' => $this->authentication,
                    'accessRightsInformation' => [
                        'identifier' => $this->constructIdentifier($path, $type),
                        'aclEntries' => [
                            'aclEntry' => $aclEntries
                        ],
                        'allLevel' => $allLevel,
                    ],
                    'applyToChildren' => $applyToChildren
                ]
            ];
        }

        return $this->doBatch($this->getBatchOptions($operations));
    }
    public function copy(array $copies = []):array
    {
        $operations = [];
        //TODO: add validateCopies()

        foreach ($copies as $copy){
            $path = $copy['path'];
            $type = $copy['type'];
            $targetContainerPath = $copy['targetContainerPath'];
            $targetContainerType = $copy['targetContainerType'];
            $targetContainerIdentifier = $this->constructIdentifier($targetContainerPath, $targetContainerType);
            $doWorkflow = $copy['doWorkflow'] ?? false;
            $newName = $copy['newName'] ?? basename($path);
            $operations[] = [
                'copy' => [
                    'authentication' => $this->authentication,
                    'identifier' => $this->constructIdentifier($path, $type),
                    'copyParameters' => $this->constructCopyParameters($targetContainerIdentifier, $newName, $doWorkflow),

                ]
            ];
        }

        return $this->doBatch($this->getBatchOptions($operations));
    }


    /**
     * Non operations
     */

    /**
     * @param array $creates
     * @return array
     */
    public function create(array $creates = []):array
    {
        $operations = [];
        foreach ($creates as $move)
        {
            $type = $move['type'];
            $asset = is_array($move['asset']) ? $move['asset'] : (object)$move['asset'];
            $operations[] = [
                'create' => [
                    'authentication' => $this->authentication,
                    'asset' => [$type => $asset]
                ],
            ];
        }

        return $this->doBatch($this->getBatchOptions($operations), __METHOD__);


    }
    public function read(array $reads = []):array
    {
        $operations = [];
        //TODO: add validateReads()

        foreach ($reads as $read)
        {
            $path = $read['path'];
            $type = $read['type'];
            $operations[] = [
                'read' => [
                    'authentication' => $this->authentication,
                    'identifier' => $this->constructIdentifier($path, $type),
                ],
            ];
        }

        return $this->doBatch($this->getBatchOptions($operations), __METHOD__);
    }
    public function readAccessRights(array $readAccessRights = []):array
    {
        $operations = [];
        foreach ($readAccessRights as $readAccessRight){
            $path = $readAccessRight['path'];
            $type = $readAccessRight['type'];
            $operations[] = [
                'readAccessRights' => [
                    'authentication' => $this->authentication,
                    'identifier' => $this->constructIdentifier($path, $type),
                ]
            ];
        }

        return $this->doBatch($this->getBatchOptions($operations), __METHOD__);
    }
    public function relationships(array $relationships = []):array
    {
        $operations = [];
        //TODO: add validateRelationships()

        foreach ($relationships as $relationship){
            $path = $relationship['path'];
            $type = $relationship['type'];
            $operations[] = [
                'relationships' => [
                    'authentication' => $this->authentication,
                    'identifier' => $this->constructIdentifier($path, $type),
                ]
            ];
        }

        return $this->doBatch($this->getBatchOptions($operations), __METHOD__);
    }

    /**
     * Validate inputs
     */

    protected function validateMoves(array $moves = []):void
    {

        foreach ($moves as $entry){
            $msg = '';

            if (!isset($entry['type'])){
                $msg = '$entry is not set';
            }
            if (!isset($entry['path'])){
                $msg = 'path is not set';
            }
            if (!isset($entry['newParentPath'])){
                $msg = 'newParentPath is not set';
            }

            if (empty($msg)){
                throw new \RuntimeException($msg);
            }

        }
    }

    protected function validateEdits(array $edits = []):void
    {

        foreach ($edits as $entry){
            $msg = '';

            if (!isset($entry['type'])){
                $msg = 'type is not set';
            }
            if (!isset($entry['asset'])){
                $msg = 'asset is not set';
            }
            if (!is_array($entry['asset']) || !is_object($entry['asset'])){
                $msg = 'asset must be an array or object';
            }

            if (empty($msg)){
                throw new \RuntimeException($msg);
            }

        }
    }

    /**
     * construct operation array
     */

    protected function constructAuthAndAsset(string $type, array | \stdClass $asset):array
    {
        return [
            'authentication' => $this->authentication,
            'asset' => [$type => $asset]
        ];
    }

    protected function constructAuthAndIdentifier(string $path, string $type):array
    {
        return [
            'authentication' => $this->authentication,
            'identifier' => $this->constructIdentifier($path, $type),
        ];
    }

    public function constructOperationCreate(string $type, array | \stdClass $asset):array
    {
        return [
            'create' => $this->constructAuthAndAsset($type, $asset),
        ];
    }

    public function constructOperationDelete(string $path, string $type):array
    {
        return [
            'delete' => [
                'authentication' => $this->authentication,
                'identifier' => $this->constructIdentifier($path, $type),
            ]
        ];
    }


    public function constructOperationEdit(string $type, array | \stdClass $asset):array
    {
        return [
            'edit' => $this->constructAuthAndAsset($type, $asset),
        ];
    }

    public function constructOperationMove(string $path, string $type, string $newParentPath, bool $doWorkflow):array
    {
        return [
            'move' => [
                'authentication' => $this->authentication,
                'identifier' => $this->constructIdentifier($path, $type),
                'moveParameters' => $this->constructMoveParameters($newParentPath, $type, $doWorkflow)
            ]
        ];

    }

    public function constructOperationCopy(string $path, string $type, array $targetContainerIdentifier, string $newName, bool $doWorkflow):array
    {
        return [
            'copy' => [
                'authentication' => $this->authentication,
                'identifier' => $this->constructIdentifier($path, $type),
                'copyParameters' => $this->constructCopyParameters($targetContainerIdentifier, $newName, $doWorkflow),

        ]];
    }

}