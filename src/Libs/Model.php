<?php
Namespace App\Libs;

class Model
{
	public $connection;
	public $connectionName = 'Mysql_HSDA';
	
	function __construct(\Fusio\Engine\ConnectorInterface $connector)
	{
		$this->connection = $connector->getConnection($this->connectionName);
	}
	
	static function create(\Fusio\Engine\ConnectorInterface $connector)
	{
		return new Model($connector);
	}
	
	
// ====== std ============================================================================	
	
	function getItemById(string $table, string $id, string $fieldset='*')
	{
		$id = addslashes($id);
		return $this->connection->fetchAssoc("SELECT {$fieldset} FROM {$table} WHERE id = :id", ['id' => $id]);
	}
	
	function getItemsByQuery(string $table, array $params, string $fieldset='*')
	{
		$query = new Query($params, $ff = self::fieldSet($table), $ff);
		if (!$query->isValid)
			return false;
		
		$count   = $this->connection->fetchColumn("SELECT COUNT(*) FROM {$table} {$query->sql['where']}");
		$totalpages = ceil($count / $query->parameters['per_page']);

		$sql = "SELECT 
						{$fieldset}
					FROM {$table}
					{$query->sql['where']}
					{$query->sql['sort_by']}
					{$query->sql['limit']}";
		//echo $sql;			
		$items = $count ? $this->connection->fetchAll($sql) : [];

		return 
			[
				'page' => $query->parameters['page'],
				'per_page' => $query->parameters['per_page'],
				'total_pages' => $totalpages,
				'total_items' => $count,
				'items' => $items,
			];
	}

	static function fieldSet($table)
	{
		$ff = [
			'contacts' => ['id', 'organization_id', 'service_id', 'service_at_location_id', 'name', 'title', 'department', 'email'],
			'locations' => ['id', 'organization_id', 'name', 'alternate_name', 'description', 'transportation', 'latitude', 'longitude'],
			'organizations' => ['id', 'name', 'alternate_name', 'description', 'email', 'url', 'tax_status', 'tax_id', 'year_incorporated', 'legal_status'],
			'services' => ['id', 'organization_id', 'program_id', 'name', 'alternate_name', 'description', 'url', 'email', 'status', 'interpretation_services', 'application_process', 'wait_time', 'fees', 'accreditations', 'licenses'],
		];
		return $ff[$table];
	}
	
	
// ====== contacts ============================================================================	
	
	function getContact(string $id)
	{
		return $this->getItemById('contacts', $id, 'id, organization_id, service_id, service_at_location_id, name, title, department, email');
	}

	function getContacts(array $params)
	{
		return $this->getItemsByQuery('contacts', $params, 'id, organization_id, service_id, service_at_location_id, name, title, department, email');
	}

	// -------------- phones ----------------

	static function contactPhoneSQL()
	{
		return 'SELECT 
				p.*, c.id as contact_id
		FROM phones p
			INNER JOIN contacts c
				ON p.service_id = c.service_id
		';
	}
	
	function getContactPhone(string $id, string $item_id)
	{
		$id = addslashes($id);
		$item_id = addslashes($item_id);
		$item = $this->connection->fetchAssoc(
					self::contactPhoneSQL() . ' WHERE c.id = :id AND p.id = :item_id',
					['id' => $id, 'item_id' => $item_id]
		);
		return $item;
	}
	
	function getContactsPhones($ids)
	{
		$ids = is_string($ids) ? addslashes($ids) : $ids;
		if ($ids === [] || $ids === '')
			return [];
		if (is_array($ids))
			return $this->connection->fetchAll(
					self::contactPhoneSQL() . ' WHERE c.id IN (' . implode(',', $ids) . ')');
		elseif (is_string($ids))
			return $this->connection->fetchAll(
					self::contactPhoneSQL() . ' WHERE c.id = :id', ['id' => $ids]);
		else
			return [];
	}

	//---- complete ---------------	
	
	function getContactComplete(string $id)
	{
		$item = $this->getContact($id);
		if (!$item)
			return false;
		$details = $this->getContactCompleteDetails($id);
		return array_merge($item, $details[$id]);
	}

	function getContactsComplete(array $params)
	{
		$services = $this->getContacts($params);
		$items = $services['items'];
		if (!$items)
			return $services;
		$ids = [];
		foreach ($items as $item)
			$ids[] = $item['id'];
		$details = $this->getContactCompleteDetails($ids);
		foreach ($items as $i=>$item)
			$items[$i] = array_merge($item, $details[$item['id']]);
		$services['items'] = $items;
		return $services;
	}

	function getContactCompleteDetails($ids)
	{
		$result = [];
		foreach ([
			'getContactsPhones' => 'phones',
		] as $method=>$key)
		{
			$items = $this->$method($ids);
			foreach ($items as $item)
				$result[$item['contact_id']][$key][] = $item;
		}
		return $result;
	}
// ====== locations ============================================================================	
	
	function getLocation(string $id)
	{
		return $this->getItemById('locations', $id);
	}

	function getLocations(array $params)
	{
		return $this->getItemsByQuery('locations', $params);
	}

	function getLocationPhone(string $id, string $item_id)
	{
		$id = addslashes($id);
		$item_id = addslashes($item_id);
		$item = $this->connection->fetchAssoc('SELECT * FROM phones WHERE location_id = :id AND id = :item_id', ['id' => $id, 'item_id' => $item_id]);
		return $item;
	}
	
	function getLocationsPhones($ids)
	{
		$ids = is_string($ids) ? addslashes($ids) : $ids;
		if ($ids === [] || $ids === '')
			return [];
		if (is_array($ids))
			return $this->connection->fetchAll('SELECT * FROM phones WHERE location_id IN (' . implode(',', $ids) . ')');
		elseif (is_string($ids))
			return $this->connection->fetchAll('SELECT * FROM phones WHERE location_id = :id', ['id' => $ids]);
		else
			return [];
	}
	
	function getLocationAccessibilityItem(string $id, string $item_id)
	{
		$id = addslashes($id);
		$item_id = addslashes($item_id);
		$item = $this->connection->fetchAssoc(
					'SELECT * FROM accessibility_for_disabilities WHERE location_id = :id AND id = :item_id', 
					['id' => $id, 'item_id' => $item_id]
		);
		return $item;
	}
	
	function getLocationsAccessibility($ids)
	{
		$ids = is_string($ids) ? addslashes($ids) : $ids;
		if ($ids === [] || $ids === '')
			return [];
		if (is_array($ids))
			return $this->connection->fetchAll('SELECT * FROM accessibility_for_disabilities WHERE location_id IN (' . implode(',', $ids) . ')');
		elseif (is_string($ids))
			return $this->connection->fetchAll('SELECT * FROM accessibility_for_disabilities WHERE location_id = :id', ['id' => $ids]);
		else
			return [];
	}
	
	function getLocationLanguage(string $id, string $item_id)
	{
		$id = addslashes($id);
		$item_id = addslashes($item_id);
		$item = $this->connection->fetchAssoc(
					'SELECT id_int as id, service_id, location_id, language FROM languages WHERE location_id = :id AND id_int = :item_id', 
					['id' => $id, 'item_id' => $item_id]
		);
		return $item;
	}
	
	function getLocationsLanguages($ids)
	{
		$ids = is_string($ids) ? addslashes($ids) : $ids;
		if ($ids === [] || $ids === '')
			return [];
		if (is_array($ids))
			return $this->connection->fetchAll(
					'SELECT id_int as id, service_id, location_id, language FROM languages WHERE location_id IN (' . implode(',', $ids) . ')');
		elseif (is_string($ids))
			return $this->connection->fetchAll(
					'SELECT id_int as id, service_id, location_id, language FROM languages WHERE location_id = :id', ['id' => $ids]);
		else
			return [];
	}
	
	
	//-------------------	
	
	static function locationServiceSQL()
	{
		return 'SELECT 
			s.id,
			s.organization_id,
			s.program_id,
			l.id as location_id,
			s.name,
			s.alternate_name,
			s.description,
			s.url,
			s.email,
			s.status,
			s.interpretation_services,
			s.application_process,
			s.wait_time,
			s.fees,
			s.accreditations,
			s.licenses
		FROM services s
			INNER JOIN locations l
				ON s.organization_id = l.organization_id
		';
	}

	function getLocationService(string $id, string $item_id)
	{
		$id = addslashes($id);
		$item_id = addslashes($item_id);
		$item = $this->connection->fetchAssoc(
					self::locationServiceSQL() . 'WHERE l.id = :id AND s.id = :item_id',
					['id' => $id, 'item_id' => $item_id]
		);
		return $item;
	}
	
	function getLocationsServices($ids)
	{
		$ids = is_string($ids) ? addslashes($ids) : $ids;
		if ($ids === [] || $ids === '')
			return [];
		if (is_array($ids))
			return $this->connection->fetchAll(
					self::locationServiceSQL() . 'WHERE l.id IN (' . implode(',', $ids) . ')');
		elseif (is_string($ids))
			return $this->connection->fetchAll(
					self::locationServiceSQL() . 'WHERE l.id = :id', ['id' => $ids]);
		else
			return [];
	}
	
	
	//-------------------	
	
	static function locationRegScheduleSQL()
	{
		return 'SELECT 
			id_int as id,
			l.service_id,
			l.location_id,
			l.id as service_at_location_id,
			weekday,
			opens_at,
			closes_at
		FROM regular_schedules s
			INNER JOIN services_at_location l
				ON s.service_id = l.service_id
		';
	}

	function getLocationRegSchedule(string $id, string $item_id)
	{
		$id = addslashes($id);
		$item_id = addslashes($item_id);
		$item = $this->connection->fetchAssoc(
					self::locationRegScheduleSQL() . 'WHERE l.location_id = :id AND id_int = :item_id',
					['id' => $id, 'item_id' => $item_id]
		);
		return $item;
	}
	
	function getLocationsRegSchedules($ids)
	{
		$ids = is_string($ids) ? addslashes($ids) : $ids;
		if ($ids === [] || $ids === '')
			return [];
		if (is_array($ids))
			return $this->connection->fetchAll(
					self::locationRegScheduleSQL() . 'WHERE l.location_id IN (' . implode(',', $ids) . ')');
		elseif (is_string($ids))
			return $this->connection->fetchAll(
					self::locationRegScheduleSQL() . 'WHERE l.location_id = :id', ['id' => $ids]);
		else
			return [];
	}

	
	//-------------------	
	
	static function locationPhysAddressSQL()
	{
		return 'SELECT 
			id,
			location_id,
			attention,
			address_1,
			address_2,
			"" as address_3,
			"" as address_4,
			city,
			region,
			state_province,
			postal_code,
			country
		FROM physical_addresses
		';
	}

	function getLocationPhysAddress(string $id, string $item_id)
	{
		$id = addslashes($id);
		$item_id = addslashes($item_id);
		$item = $this->connection->fetchAssoc(
					self::locationPhysAddressSQL() . 'WHERE location_id = :id AND id = :item_id',
					['id' => $id, 'item_id' => $item_id]
		);
		return $item;
	}
	
	function getLocationsPhysAddresses($ids)
	{
		$ids = is_string($ids) ? addslashes($ids) : $ids;
		if ($ids === [] || $ids === '')
			return [];
		if (is_array($ids))
			return $this->connection->fetchAll(
					self::locationPhysAddressSQL() . 'WHERE location_id IN (' . implode(',', $ids) . ')');
		elseif (is_string($ids))
			return $this->connection->fetchAll(
					self::locationPhysAddressSQL() . 'WHERE location_id = :id', ['id' => $ids]);
		else
			return [];
	}
	
	
	//---- complete ---------------	
	
	function getLocationComplete(string $id)
	{
		$item = $this->getLocation($id);
		$details = $this->getLocationCompleteDetails($id);
		return array_merge($item, $details[$id]);
	}

	function getLocationsComplete(array $params)
	{
		$locations = $this->getLocations($params);
		$items = $locations['items'];
		$ids = [];
		foreach ($items as $item)
			$ids[] = $item['id'];
		$details = $this->getLocationCompleteDetails($ids);
		foreach ($items as $i=>$item)
			$items[$i] = array_merge($item, $details[$item['id']]);
		$locations['items'] = $items;
		return $locations;
	}

	function getLocationCompleteDetails($ids)
	{
		$result = [];
		foreach ([
			'getLocationsRegSchedules' => 'regular_schedule',
			'getLocationsLanguages' => 'languages',
			'getLocationsPhysAddresses' => 'physical_address',
			'getLocationsPhones' => 'phones',
			'getLocationsServices' => 'service',
			'getLocationsAccessibility' => 'accessibility_for_disabilities',
		] as $method=>$key)
		{
			$items = $this->$method($ids);
			foreach ($items as $item)
				$result[$item['location_id']][$key][] = $item;
		}
		return $result;
	}

	
// ====== organizations ============================================================================	
	
	function getOrganization(string $id)
	{
		return $this->getItemById('organizations', $id);
	}

	function getOrganizations(array $params)
	{
		return $this->getItemsByQuery('organizations', $params);
	}


	// -------------- phones ----------------
	
	static function organizationsPhoneSQL()
	{
		return 'SELECT 
			p.*,
			l.organization_id as organization_id
		FROM phones p
			INNER JOIN locations l
			ON p.location_id = l.id
		';
	}

	function getOrganizationsPhone(string $id, string $item_id)
	{
		$id = addslashes($id);
		$item_id = addslashes($item_id);
		$item = $this->connection->fetchAssoc(self::organizationsPhoneSQL() . 'WHERE l.organization_id = :id AND p.id = :item_id', ['id' => $id, 'item_id' => $item_id]);
		return $item;
	}
	
	function getOrganizationsPhones($ids)
	{
		$ids = is_string($ids) ? addslashes($ids) : $ids;
		if ($ids === [] || $ids === '')
			return [];
		if (is_array($ids))
			return $this->connection->fetchAll(self::organizationsPhoneSQL() . 'WHERE l.organization_id IN (' . implode(',', $ids) . ')');
		elseif (is_string($ids))
			return $this->connection->fetchAll(self::organizationsPhoneSQL() . 'WHERE l.organization_id = :id', ['id' => $ids]);
		else
			return [];
	}
	

	// -------------- locations ----------------
	
	function getOrganizationsLocation(string $id, string $item_id)
	{
		$id = addslashes($id);
		$item_id = addslashes($item_id);
		$item = $this->connection->fetchAssoc('SELECT * FROM locations WHERE organization_id = :id AND id = :item_id', ['id' => $id, 'item_id' => $item_id]);
		return $item;
	}
	
	function getOrganizationsLocations($ids)
	{
		$ids = is_string($ids) ? addslashes($ids) : $ids;
		if ($ids === [] || $ids === '')
			return [];
		if (is_array($ids))
			return $this->connection->fetchAll('SELECT * FROM locations WHERE organization_id IN (' . implode(',', $ids) . ')');
		elseif (is_string($ids))
			return $this->connection->fetchAll('SELECT * FROM locations WHERE organization_id = :id', ['id' => $ids]);
		else
			return [];
	}
	

	// -------------- contacts ----------------
	
	static function organizationsContactsSQL()
	{
		return 'SELECT 
			c.id, c.organization_id, c.service_id, c.service_at_location_id, c.name, c.title, c.department, c.email,
			s.organization_id as organization_id
		FROM contacts c
			INNER JOIN services s
			ON c.service_id = s.id
		';
	}

	function getOrganizationsContact(string $id, string $item_id)
	{
		$id = addslashes($id);
		$item_id = addslashes($item_id);
		$item = $this->connection->fetchAssoc(self::organizationsContactsSQL() . 'WHERE s.organization_id = :id AND c.id = :item_id', ['id' => $id, 'item_id' => $item_id]);
		return $item;
	}
	
	function getOrganizationsContacts($ids)
	{
		$ids = is_string($ids) ? addslashes($ids) : $ids;
		if ($ids === [] || $ids === '')
			return [];
		if (is_array($ids))
			return $this->connection->fetchAll(self::organizationsContactsSQL() . 'WHERE s.organization_id IN (' . implode(',', $ids) . ')');
		elseif (is_string($ids))
			return $this->connection->fetchAll(self::organizationsContactsSQL() . 'WHERE s.organization_id = :id', ['id' => $ids]);
		else
			return [];
	}

	
	// -------------- services ----------------
	
	function getOrganizationsService(string $id, string $item_id)
	{
		$id = addslashes($id);
		$item_id = addslashes($item_id);
		$item = $this->connection->fetchAssoc(self::locationServiceSQL() . 'WHERE s.organization_id = :id AND s.id = :item_id', ['id' => $id, 'item_id' => $item_id]);
		return $item;
	}
	
	function getOrganizationsServices($ids)
	{
		$ids = is_string($ids) ? addslashes($ids) : $ids;
		if ($ids === [] || $ids === '')
			return [];
		if (is_array($ids))
			return $this->connection->fetchAll(self::locationServiceSQL() . 'WHERE s.organization_id IN (' . implode(',', $ids) . ')');
		elseif (is_string($ids))
			return $this->connection->fetchAll(self::locationServiceSQL() . 'WHERE s.organization_id = :id', ['id' => $ids]);
		else
			return [];
	}
	

	//---- complete ---------------	
	
	function getOrganizationComplete(string $id)
	{
		$item = $this->getOrganization($id);
		$details = $this->getOrganizationCompleteDetails($id);
		return array_merge($item, $details[$id] ?? []);
	}

	function getOrganizationsComplete(array $params)
	{
		$organizations = $this->getOrganizations($params);
		$items = $organizations['items'];
		$ids = [];
		foreach ($items as $item)
			$ids[] = $item['id'];
		$details = $this->getOrganizationCompleteDetails($ids);
		foreach ($items as $i=>$item)
			$items[$i] = array_merge($item, $details[$item['id']]);
		$organizations['items'] = $items;
		return $organizations;
	}

	function getOrganizationCompleteDetails($ids)
	{
		$result = [];
		foreach ([
			'getOrganizationsContacts' => 'contacts',
			'getOrganizationsLocations' => 'locations',
			'getOrganizationsServices' => 'services',
		] as $method=>$key)
		{
			$items = $this->$method($ids);
			foreach ($items as $item)
				$result[$item['organization_id']][$key][] = $item;
		}
		return $result;
	}


// ====== services ============================================================================	
	
	function getService(string $id)
	{
		return $this->getItemById('services', $id);
	}

	function getServices(array $params)
	{
		if (preg_match('~organization:~si', ($params['query'] ?? '') . ($params['queries'] ?? '')))
			return $this->getServicesByOrganization($params);
		else
			return $this->getItemsByQuery('services', $params);
	}

	function getServicesByOrganization(array $params)
	{
		$ff = array_merge(self::fieldSet('services'), ['organization']);
		$query = new Query($params, $ff, $ff);
		if (!$query->isValid)
			return false;
		
		$table = 'services s INNER JOIN (SELECT id as org_id, name as organization FROM organizations) o ON s.organization_id=o.org_id';
		$count   = $this->connection->fetchColumn("SELECT COUNT(*) FROM {$table} {$query->sql['where']}");
		$totalpages = ceil($count / $query->parameters['per_page']);

		$sql = "SELECT 
						s.*
					FROM {$table}
					{$query->sql['where']}
					{$query->sql['sort_by']}
					{$query->sql['limit']}";
		//echo $sql;			
		$items = $count ? $this->connection->fetchAll($sql) : [];

		return 
			[
				'page' => $query->parameters['page'],
				'per_page' => $query->parameters['per_page'],
				'total_pages' => $totalpages,
				'total_items' => $count,
				'items' => $items,
			];
	}



	// -------------- contacts ----------------

	function getServicesContact(string $id, string $item_id)
	{
		$id = addslashes($id);
		$item_id = addslashes($item_id);
		$item = $this->connection->fetchAssoc('SELECT 
				id, organization_id, service_id, service_at_location_id, name, title, department, email 
				FROM contacts WHERE service_id = :id AND id = :item_id', ['id' => $id, 'item_id' => $item_id]);
		return $item;
	}
	
	function getServicesContacts($ids)
	{
		$ids = is_string($ids) ? addslashes($ids) : $ids;
		if ($ids === [] || $ids === '')
			return [];
		if (is_array($ids))
			return $this->connection->fetchAll('SELECT 
				id, organization_id, service_id, service_at_location_id, name, title, department, email
				FROM contacts WHERE service_id IN (' . implode(',', $ids) . ')');
		elseif (is_string($ids))
			return $this->connection->fetchAll('SELECT 
				id, organization_id, service_id, service_at_location_id, name, title, department, email
				FROM contacts WHERE service_id = :id', ['id' => $ids]);
		else
			return [];
	}

	// -------------- languages ----------------

	function getServiceLanguage(string $id, string $item_id)
	{
		$id = addslashes($id);
		$item_id = addslashes($item_id);
		$item = $this->connection->fetchAssoc(
					'SELECT id_int as id, service_id, location_id, language FROM languages WHERE service_id = :id AND id_int = :item_id',
					['id' => $id, 'item_id' => $item_id]
		);
		return $item;
	}
	
	function getServicesLanguages($ids)
	{
		$ids = is_string($ids) ? addslashes($ids) : $ids;
		if ($ids === [] || $ids === '')
			return [];
		if (is_array($ids))
			return $this->connection->fetchAll(
					'SELECT id_int as id, service_id, location_id, language FROM languages WHERE service_id IN (' . implode(',', $ids) . ')');
		elseif (is_string($ids))
			return $this->connection->fetchAll(
					'SELECT id_int as id, service_id, location_id, language FROM languages WHERE service_id = :id', ['id' => $ids]);
		else
			return [];
	}

	// -------------- phones ----------------

	function getServicePhone(string $id, string $item_id)
	{
		$id = addslashes($id);
		$item_id = addslashes($item_id);
		$item = $this->connection->fetchAssoc(
					'SELECT * FROM phones WHERE service_id = :id AND id = :item_id',
					['id' => $id, 'item_id' => $item_id]
		);
		return $item;
	}
	
	function getServicesPhones($ids)
	{
		$ids = is_string($ids) ? addslashes($ids) : $ids;
		if ($ids === [] || $ids === '')
			return [];
		if (is_array($ids))
			return $this->connection->fetchAll(
					'SELECT * FROM phones WHERE service_id IN (' . implode(',', $ids) . ')');
		elseif (is_string($ids))
			return $this->connection->fetchAll(
					'SELECT * FROM phones WHERE service_id = :id', ['id' => $ids]);
		else
			return [];
	}

	// -------------- regular schedules ----------------

	function getServiceRegSchedule(string $id, string $item_id)
	{
		$id = addslashes($id);
		$item_id = addslashes($item_id);
		$item = $this->connection->fetchAssoc(
					self::locationRegScheduleSQL() . 'WHERE s.service_id = :id AND id_int = :item_id',
					['id' => $id, 'item_id' => $item_id]
		);
		return $item;
	}
	
	function getServicesRegSchedules($ids)
	{
		$ids = is_string($ids) ? addslashes($ids) : $ids;
		if ($ids === [] || $ids === '')
			return [];
		if (is_array($ids))
			return $this->connection->fetchAll(
					self::locationRegScheduleSQL() . 'WHERE s.service_id IN (' . implode(',', $ids) . ')');
		elseif (is_string($ids))
			return $this->connection->fetchAll(
					self::locationRegScheduleSQL() . 'WHERE s.service_id = :id', ['id' => $ids]);
		else
			return [];
	}

	// -------------- service_areas ----------------

	function getServicesArea(string $id, string $item_id)
	{
		$id = addslashes($id);
		$item_id = addslashes($item_id);
		$item = $this->connection->fetchAssoc(
			'SELECT id, service_id, service_area, description FROM service_areas WHERE service_id = :id AND id_int = :item_id',
			['id' => $id, 'item_id' => $item_id]
		);
		return $item;
	}
	
	function getServicesAreas($ids)
	{
		$ids = is_string($ids) ? addslashes($ids) : $ids;
		if ($ids === [] || $ids === '')
			return [];
		if (is_array($ids))
			return $this->connection->fetchAll(
					'SELECT id, service_id, service_area, description FROM service_areas WHERE service_id IN (' . implode(',', $ids) . ')');
		elseif (is_string($ids))
			return $this->connection->fetchAll(
					'SELECT id, service_id, service_area, description FROM service_areas WHERE service_id = :id', ['id' => $ids]);
		else
			return [];
	}

	// -------------- taxonomy ----------------

	static function locationTaxonomySQL()
	{
		return 'SELECT 
			t.*, s.service_id
		FROM taxonomy t
			INNER JOIN services_taxonomy s
			ON s.taxonomy_id = t.id
		';
	}

	function getServiceTaxonomyItem(string $id, string $item_id)
	{
		$id = addslashes($id);
		$item_id = addslashes($item_id);
		$item = $this->connection->fetchAssoc(
					self::locationTaxonomySQL() . 'WHERE s.service_id = :id AND t.id = :item_id',
					['id' => $id, 'item_id' => $item_id]
		);
		return $item;
	}
	
	function getServicesTaxonomy($ids)
	{
		$ids = is_string($ids) ? addslashes($ids) : $ids;
		if ($ids === [] || $ids === '')
			return [];
		if (is_array($ids))
			return $this->connection->fetchAll(
					self::locationTaxonomySQL() . 'WHERE s.service_id IN (' . implode(',', $ids) . ')');
		elseif (is_string($ids))
			return $this->connection->fetchAll(
					self::locationTaxonomySQL() . 'WHERE s.service_id = :id', ['id' => $ids]);
		else
			return [];
	}


	// -------------- organization ----------------

	function getServicesOrganizations($ids)
	{
		$ids = is_string($ids) ? addslashes($ids) : $ids;
		if ($ids === [] || $ids === '')
			return [];
		$sql = 'SELECT o.*, s.id as service_id FROM organizations o INNER JOIN services s ON o.id=s.organization_id ';
		if (is_array($ids))
			return $this->connection->fetchAll($sql . 'WHERE s.id IN (' . implode(',', $ids) . ')');
		elseif (is_string($ids))
			return $this->connection->fetchAll($sql . 'WHERE s.id = :id', ['id' => $ids]);
		else
			return [];
	}

	// -------------- location ----------------

	function getServicesLocations($ids)
	{
		$ids = is_string($ids) ? addslashes($ids) : $ids;
		if ($ids === [] || $ids === '')
			return [];
		$sql = 'SELECT l.*, s.service_id FROM locations l INNER JOIN services_at_location s ON l.id=s.location_id ';
		if (is_array($ids))
			return $this->connection->fetchAll($sql . 'WHERE service_id IN (' . implode(',', $ids) . ')');
		elseif (is_string($ids))
			return $this->connection->fetchAll($sql . 'WHERE service_id = :id', ['id' => $ids]);
		else
			return [];
	}


	//---- complete ---------------	
	
	function getServiceComplete(string $id)
	{
		$item = $this->getService($id);
		if (!$item)
			return false;
		$details = $this->getServiceCompleteDetails($id);
		return array_merge($item, $details[$id]);
	}

	function getServicesComplete(array $params)
	{
		$services = $this->getServices($params);
		$items = $services['items'];
		if (!$items)
			return $services;
		$ids = [];
		foreach ($items as $item)
			$ids[] = $item['id'];
		$details = $this->getServiceCompleteDetails($ids);
		foreach ($items as $i=>$item)
			$items[$i] = array_merge($item, $details[$item['id']]);
		$services['items'] = $items;
		return $services;
	}

	function getServiceCompleteDetails($ids)
	{
		$result = [];
		foreach ([
			'getServicesContacts' => 'contacts',
			'getServicesRegSchedules' => 'regular_schedule',
			'getServicesLanguages' => 'languages',
			'getServicesPhones' => 'phones',
			'getServicesAreas' => 'service_area',
			'getServicesTaxonomy' => 'taxonomy',
			'getServicesOrganizations' => 'organization',
			'getServicesLocations' => 'location',
		] as $method=>$key)
		{
			$items = $this->$method($ids);
			foreach ($items as $item)
				$result[$item['service_id']][$key][] = array_diff_key($item, ['service_id' => 1]);
		}
		return $result;
	}


// ====== search ============================================================================	

	function getCountByQuery(string $table, array $params)
	{
		$query = new Query($params, $ff = self::fieldSet($table), $ff);
		if (!$query->isValid)
			return false;
		return $this->connection->fetchColumn("SELECT COUNT(*) FROM {$table} {$query->sql['where']}");
	}


	function getSearchResults($params)
	{
		$params = Query::initParams($params);
		$trgSt = $params['per_page'] * ($params['page'] - 1);
		$trgEnd = $params['per_page'] * $params['page'];
		
		$currSt = $currEnd = -1;
		$total = 0;
		$items = [];
		
		foreach (['organizations', 'locations', 'services'] as $table)
		{
			$instParams = Query::tweakParameters($params, $ff = self::fieldSet($table), $ff);
			if (!$instParams['query'] && !$instParams['queries'])
				continue;
			$currCount = $this->getCountByQuery($table, $instParams);
			
			if ($currCount)
			{
				$currSt = $currEnd + 1;
				$currEnd += $currCount;
				$total += $currCount;
			}
			//print_r(compact('currSt', 'currEnd', 'trgSt', 'trgEnd'));echo '<br/>';
			if ($currEnd < $trgSt || $currSt >= $trgEnd)
				continue;
			
			$instParams['search_offset'] = $offs = $trgSt - $currSt;
			$instParams['per_page'] = $len = min($trgEnd, $currEnd + 1) - $trgSt;
			$method = sprintf('get%sComplete', ucwords($table));
			//echo $method . '<br/>';
			//print_r($instParams);
			$items[$table] = $this->$method($instParams)['items'];
			//echo count($items[$table]) . '<br/>';

			$trgSt += $len;
		}
		
		return 
			[
				'page' => $params['page'],
				'per_page' => $params['per_page'],
				'total_pages' => ceil($total / $params['per_page']),
				'total_items' => $total,
				'items' => $items,
			];
	}

// ====== taxonomy ============================================================================	
	
	function getTaxonomyItem(string $id)
	{
		return $this->getItemById('taxonomy', $id);
	}

	function getTaxonomy(array $params)
	{
		$items = $this->connection->fetchAll('SELECT * FROM taxonomy');
		return [
			'total_items' => count($items),
			'items' => $items
		];
	}

	function getServicesByTaxonomy(string $tName, array $params)
	{
		$query = new Query($params, $ff = self::fieldSet('services'), $ff);
		$tName = addslashes(urldecode($tName));
		if (!$query->strictSearch)
			$tName = "%{$tName}%";
		if (!$query->isValid)
			return false;
		
		$idsIdxd = $query->taxonomyFamily
			? $this->connection->fetchAll($sql = "SELECT
						s.service_id
					FROM taxonomy t
						INNER JOIN taxonomy_family f
							ON t.id = f.ancestor_id
						INNER JOIN services_taxonomy s
							ON f.desc_id = s.taxonomy_id
					WHERE t.name LIKE '{$tName}'
					GROUP BY s.service_id")
			: $this->connection->fetchAll($sql = "SELECT
						s.service_id
					FROM taxonomy t
						INNER JOIN services_taxonomy s
						ON t.id = s.taxonomy_id
					WHERE t.name LIKE '{$tName}'
					GROUP BY s.service_id");
		//echo $sql . '<br/>';
		//var_dump($query);
		
		if (!$idsIdxd)
			return false;

		$ids = [];
		foreach ($idsIdxd as $idIdxd)
			$ids[] = $idIdxd['service_id'];
		//print_r($ids);
		
		$query->sql['where'] =
				(string)$query->sql['where'] .
				($query->sql['where'] ? ' AND' : 'WHERE') .
				sprintf(' id IN (%s)', 
					implode(',', $ids)
				);
		
		$count = $this->connection->fetchColumn("SELECT COUNT(*) FROM services {$query->sql['where']}");
		if (!$count)
			return false;

		$totalpages = ceil($count / $query->parameters['per_page']);

		$sql = "SELECT 
						*
					FROM services
					{$query->sql['where']}
					{$query->sql['sort_by']}
					{$query->sql['limit']}";
					
		$items = $count ? $this->connection->fetchAll($sql) : [];

		return 
			[
				'page' => $query->parameters['page'],
				'per_page' => $query->parameters['per_page'],
				'total_pages' => $totalpages,
				'total_items' => $count,
				'items' => $items,
			];
	}

	function getServicesCompleteByTaxonomy(string $tName, array $params)
	{
		$services = $this->getServicesByTaxonomy($tName, $params);
		$items = $services['items'];
		if (!$items)
			return $services;
		$ids = [];
		foreach ($items as $item)
			$ids[] = $item['id'];
		$details = $this->getServiceCompleteDetails($ids);
		foreach ($items as $i=>$item)
			$items[$i] = array_merge($item, $details[$item['id']]);
		$services['items'] = $items;
		return $services;
	}

}