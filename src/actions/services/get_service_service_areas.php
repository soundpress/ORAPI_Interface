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

$items = Model::create($connector)
	->getServicesAreas($request->getUriFragment('id'));

if (empty($items))
    throw new StatusCode\InternalServerErrorException('Internal Server Error');

$items = is_array($items) ? $items : [];
return $response->build(200, [], [
	'total_items' => count($items),
	'items' => $items
]);