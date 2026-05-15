<?php

declare(strict_types = 1);

namespace Edu\IU\Wcms\WebService;

trait WCMSClientTraits
{

    protected function getContainerType(string $childType): string
    {
        return match (true){
            in_array($childType, ['page', 'folder', 'file', 'symlink', 'format', 'block', 'template']) => 'folder',
            str_ends_with($childType, 'container') => $childType,
            default => $childType . 'container',
        };

    }



    /**
     * Construct reusable arrays
     */


    /**
     * @param string $id
     * @param string $type
     * @return string[]
     */
    protected function constructIdentifierWithId(string $id, string $type): array
    {
        return  [
            'type' => $type,
            'id' => $id,
        ];
    }

    /**
     *
     * construct identifier array
     * @param string $path
     * @param string $type
     * @param string $siteName
     * @return array
     */
    protected function constructIdentifier(string $path, string $type, string $siteName = ''): array
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
     * @param string $newParentPath
     * @param string $type
     * @param bool $doWorkflow
     * @return array
     */
    protected function constructMoveParameters(string $newParentPath, string $type, bool $doWorkflow = false): array
    {
        return [
            'doWorkflow' => $doWorkflow,
            'destinationContainerIdentifier' => $this->constructIdentifier($newParentPath, $this->getContainerType($type)),
        ];
    }


    /**
     * @param string $type
     * @return string
     */
    protected function constructContainerType(string $type): string{
        if ($type == 'transport_ftp'){
            $type = 'transport';
        }
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
            'transport'
        ];
        $containers = [
            'metadatasetcontainer',
            'pageconfigurationsetcontainer',
            'datadefinitioncontainer',
            'sharedfieldcontainer',
            'contenttypecontainer',
            'assetfactorycontainer',
            'transportcontainer',
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


    protected function constructPublishInformation(string $path, string $type, array $destinations = [], bool $isUnpublish = false): array
    {
        return [
            'identifier' => $this->constructIdentifier($path, $type),
            'unpublish' => $isUnpublish,
            'destinations' => $destinations
        ];
    }

    protected function constructCopyParameters(array $targetContainerIdentifier, string $newName = '', bool $doWorkflow = false): array
    {
        return [
            'destinationContainerIdentifier' => $targetContainerIdentifier,
            'doWorkflow' => $doWorkflow,
            'newName' => $newName
        ];
    }

    /**
     * Validate inputs
     */



    /**
     * @param array $identifier
     * @return void
     */
    protected function validateIdentifier(array $identifier):void
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
            if (!$entry instanceof \stdClass){
                $msg = "Each entry of aclEntries must be \stdClass object.";
                throw new \RuntimeException($msg);
            }
            if (!in_array($entry->level, ['write', 'read'])){
                $msg = "aclEntry level value not supported. It must be one of 'write', 'read'. " . $entry->level . ' is provided.';
                throw new \RuntimeException($msg);
            }

            if (!in_array($entry->type, ['user', 'group'])){
                $msg = "aclEntry type value not supported. It must be one of 'user', 'group'. " . $entry->type . ' is provided.';
                throw new \RuntimeException($msg);
            }

            //TODO: check $entry['name']

        }
    }
}