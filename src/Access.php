<?php

declare(strict_types = 1);

namespace Edu\IU\Wcms\WebService;

class Access extends WCMSClientOperationAbstract {



    public function read(string $path, string $type):\stdClass
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

    public function readById(string $id, string $type):\stdClass
    {
        $options = [
            'authentication' => $this->authentication,
            'identifier' => $this->constructIdentifierWithId($id, $type)
        ];

        $result = $this->client->readAccessRights($options);

        if ($result->readAccessRightsReturn->success === 'true') {
            return $result->readAccessRightsReturn->accessRightsInformation;
        } else {
            throw new \RuntimeException($result->readAccessRightsReturn->message);
        }
    }


    public function saveById(string $id, string $type, array $aclEntries, string $allLevel, bool $applyToChildren = false):void
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
}