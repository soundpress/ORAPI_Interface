<?php
/**
 * @var \Fusio\Engine\ConnectorInterface $connector
 * @var \Fusio\Engine\ContextInterface $context
 * @var \Fusio\Engine\RequestInterface $request
 * @var \Fusio\Engine\Response\FactoryInterface $response
 * @var \Fusio\Engine\ProcessorInterface $processor
 * @var \Psr\Log\LoggerInterface $logger
 * @var \Psr\SimpleCache\CacheInterface $cache
 */

use App\Libs\Model;
use PSX\Http\Exception as StatusCode;

$data = Model::create($connector)
		->getAutocompletePrefetch($request->getUriFragment('section'));

if (!$data)
	throw new StatusCode\InternalServerErrorException('Internal Server Error');

return $response->build(200, [], $data);