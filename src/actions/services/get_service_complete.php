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

$id = $request->getUriFragment('id');
if (preg_match('~^\d+$~', $id))
{
	$item = Model::create($connector)
			->getServiceComplete($id);

	if (empty($item))
		throw new StatusCode\InternalServerErrorException('Internal Server Error');

	return $response->build(200, [], $item);
}
else
{
	$queryParams = $request->getParameters();
	$data = Model::create($connector)
			->getServicesCompleteByTaxonomy(urldecode($id), $queryParams);

	if (!$data)
		throw new StatusCode\InternalServerErrorException('Internal Server Error');

	return $response->build(200, [], $data);
}