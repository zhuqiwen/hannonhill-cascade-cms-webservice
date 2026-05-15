<?php

declare(strict_types = 1);

namespace Edu\IU\Wcms\WebService;

abstract class WCMSClientOperationAbstract {

    protected \SoapClient $client;
    protected array $authentication;
    protected string $site_name;

    use WCMSClientTraits;

    public function __construct(\SoapClient $client, array $authentication, string $site_name)
    {
        $this->client = $client;
        $this->authentication = $authentication;
        $this->site_name = $site_name;
    }

}