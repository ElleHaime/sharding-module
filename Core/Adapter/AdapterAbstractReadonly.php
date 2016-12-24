<?php 

namespace Sharding\Core\Adapter;

use Sharding\Core\Adapter\AdapterAbstract;

abstract class AdapterAbstractReadonly extends AdapterAbstract 
{
	public final function saveRecord()
	{
		return;
	}
	
	public final function saveRec()
	{
		return;
	}
	
	public final function deleteRecord()
	{
		return;
	}
	
	public final function updateRecord()
	{
		return;
	}
	
	public final function createShardTable($tblName, $data)
	{
		return;
	}
	
	public final function createTableBySample($tblName)
	{
		return;
	}
	
	public final function createShardMap($tblName, $data)
	{
		return;
	}
}