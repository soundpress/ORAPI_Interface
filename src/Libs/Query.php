<?php
Namespace App\Libs;

class Query
{
	public $parameters;
	public $isValid = true;
	public $strictSearch = false;
	public $taxonomyFamily = false;
	static public $maxPerPage = 100;
	public $sql;
	
	function __construct(array $parameters, array $allowedFieldsQuery=[], array $allowedFieldsSort=[])
	{
		$this->parameters = $parameters = self::initParams($parameters);
		$this->isValid = self::checkQuery($parameters['query'], $allowedFieldsQuery)
						&& self::checkQueries($parameters['queries'], $allowedFieldsQuery)
						&& !($parameters['query']  && $parameters['queries'])
						&& self::checkSortby($this->parameters['sort_by'], $this->parameters['order'], $allowedFieldsSort)
						&& self::checkOddFields($parameters)
						&& $parameters['page'] > 0
						&& $parameters['per_page'] > 0
						&& $parameters['per_page'] <= self::$maxPerPage;
		$this->strictSearch = preg_match('~strictSearchMode:true~si', "{$parameters['query']}#{$parameters['queries']}");
		$this->taxonomyFamily = preg_match('~taxonomyFamily:true~si', "{$parameters['query']}#{$parameters['queries']}");
		//file_put_contents(__DIR__ . '/q.txt', print_r($parameters, true));
		if ($this->isValid)
			$this->genSQL();
	}
	
	static function initParams($params)
	{
		$params = array_merge([
				'query' => '',
				'queries' => '',
				'page' => 1,
				'per_page' => 50,
				'sort_by' => 'id',
				'order' => 'asc',
			],
			$params
		);
		$params['per_page'] = min($params['per_page'], self::$maxPerPage);
		foreach ($params as $k=>$v)
			$params[$k] = addslashes($v);
		return $params;
	}
	
	function genSQL()
	{
		$sql = [];
		$queries = array_merge(
						(array)$this->parameters['query'],
						explode('|', $this->parameters['queries'])
					);
		$queriesSQL = [];			
		foreach ($queries as $q)
			if ($q && !preg_match('~strictSearchMode:|taxonomyFamily:~si', $q))
				$queriesSQL[] = $this->genSQLQuery($q);

		$sql['where'] = $queriesSQL ? 'WHERE ' . implode(' AND ', $queriesSQL) : '';
		
		$sql['sort_by'] = "ORDER BY {$this->parameters['sort_by']} {$this->parameters['order']}";
		
		$sql['limit'] = sprintf(
							'LIMIT %u,%u',
							
							isset($this->parameters['search_offset'])
								? $this->parameters['search_offset'] 
								:($this->parameters['page'] - 1) * $this->parameters['per_page'],
								
							$this->parameters['per_page']
						);
		$this->sql = $sql;
		return $sql;
	}
	
	function genSQLQuery($q)
	{
		list($field, $req) = explode(':', $q);
		$req = addslashes(urldecode($req));
		if ($req && !$this->strictSearch) 
			$req = "%{$req}%";
		return $req !== '' ? "{$field} LIKE \"{$req}\"" : "{$field} LIKE \"\"";
	}
	
// ==== tweaks ========================================================================================	

	static function tweakParameters($params, $fieldsQuery, $fieldsSort)
	{
		$params['query'] = $params['query'] ? self::tweakQuery($params['query'], $fieldsQuery) : '';
			
		$params['queries'] = $params['queries'] ? self::tweakQueries($params['queries'], $fieldsQuery) : '';
			
		$params['sort_by'] = self::tweakSortBy($params['sort_by'], $fieldsSort);
		
		return $params;
	}

	static function tweakQuery($q, array $allowedFields)
	{
		list($field, $req) = explode(':', $q);
		return array_search($field, $allowedFields) ? $q : null;
	}

	static function tweakQueries($qq, array $allowedFields)
	{
		$rr = [];
		foreach (explode('|', $qq) as $q)
			if ($r = self::tweakQuery($q, $allowedFields))
				$rr[] = $r;
		return implode(',', $rr);
	}

	static function tweakSortby($field, $allowedFields)
	{
		return array_search($field, $allowedFields) ? $field : 'id';
	}
	

// ==== checkers ========================================================================================	

	static function checkQuery($q, $allowedFields)
	{
		if (!$q || preg_match('~strictSearchMode:|taxonomyFamily:~si', $q))
			return true;
		if (!preg_match('~:~', $q))
			return false;
		list($field, $req) = explode(':', $q);
		if ($allowedFields && (array_search($field, $allowedFields) === false))
			return false;
		return true;
	}
	
	static function checkQueries($qq, $allowedFields)
	{
		if (!$qq)
			return true;
		$result = true;
		foreach (explode('|', $qq) as $q)
			$result = $result && self::checkQuery($q, $allowedFields);
		return $result;
	}
	
	static function checkSortby($field, $order, $allowedFields)
	{
		if (!$field || !$order)
			return false;
		if ($allowedFields && (array_search($field, $allowedFields) === false))
			return false;
		if (!preg_match('~^(asc|desc)$~', $order))
			return false;
		return true;
	}

	static function checkOddFields($parameters)
	{
		return !array_diff_key(
			$parameters, 
			array_fill_keys([
				'query',
				'queries',
				'search_offset',
				'page',
				'per_page',
				'sort_by',
				'order',
			], true)
		);
	}
}