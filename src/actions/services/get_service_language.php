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

use PSX\Http\Exception as StatusCode;
use App\Libs\Model;

$item = Model::create($connector)
	->getServiceLanguage($request->getUriFragment('id'), $request->getUriFragment('item_id'));

if (empty($item))
    throw new StatusCode\InternalServerErrorException('Internal Server Error');

return $response->build(200, [], $item);