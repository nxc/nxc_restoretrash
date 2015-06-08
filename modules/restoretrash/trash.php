<?php
/**
 * @copyright Copyright (C) 1999-2012 eZ Systems AS. All rights reserved.
 * @license http://www.gnu.org/licenses/gpl-2.0.txt GNU General Public License v2
 * @version  2012.4
 * @package kernel
 */


$Module = $Params['Module'];
$Offset = $Params['Offset'];
if ( isset( $Params['UserParameters'] ) )
{
    $UserParameters = $Params['UserParameters'];
}
else
{
    $UserParameters = array();
}
$viewParameters = array( 'offset' => $Offset, 'namefilter' => false );
$viewParameters = array_merge( $viewParameters, $UserParameters );
$db = eZDB::instance();

$http = eZHTTPTool::instance();

$user = eZUser::currentUser();
$userID = $user->id();

if ( $http->hasPostVariable( 'RemoveButton' )  )
{
    if ( $http->hasPostVariable( 'DeleteIDArray' ) )
    {
        $access = $user->hasAccessTo( 'content', 'cleantrash' );
        if ( $access['accessWord'] == 'yes' || $access['accessWord'] == 'limited' )
        {
            $deleteIDArray = $http->postVariable( 'DeleteIDArray' );

            foreach ( $deleteIDArray as $deleteID )
            {

                $objectList = eZPersistentObject::fetchObjectList( eZContentObject::definition(),
                                                                   null,
                                                                   array( 'id' => $deleteID ),
                                                                   null,
                                                                   null,
                                                                   true );
                foreach ( $objectList as $object )
                {
                    $object->purge();
                }
            }
        }
        else
        {
            return $Module->handleError( eZError::KERNEL_ACCESS_DENIED, 'kernel' );
        }
    }
}
else if ( $http->hasPostVariable( 'EmptyButton' )  )
{
    $access = $user->hasAccessTo( 'content', 'cleantrash' );
    if ( $access['accessWord'] == 'yes' || $access['accessWord'] == 'limited' )
    {
        while ( true )
        {
            // Fetch 100 objects at a time, to limit transaction size
            $objectList = eZPersistentObject::fetchObjectList( eZContentObject::definition(),
                                                               null,
                                                               array( 'status' => eZContentObject::STATUS_ARCHIVED ),
                                                               null,
                                                               100,
                                                               true );
            if ( count( $objectList ) < 1 )
                break;

            foreach ( $objectList as $object )
            {
                $object->purge();
            }
        }
    }
    else
    {
        return $Module->handleError( eZError::KERNEL_ACCESS_DENIED, 'kernel' );
    }
}
else if ( $http->hasPostVariable( 'RestoreSubtreeButton' )  ) {	
	if ( $http->hasPostVariable( 'DeleteIDArray' ) )
    {
        $access = $user->hasAccessTo( 'content', 'restore' );
        if ( $access['accessWord'] == 'yes' || $access['accessWord'] == 'limited' )
        {
            $deleteIDArray = $http->postVariable( 'DeleteIDArray' );
			$query = sprintf( 'SELECT contentobject_id FROM ezcontentobject_trash WHERE contentobject_id IN ('.join(",",$deleteIDArray).') ORDER BY depth' );
			$rows = $db->arrayQuery( $query );
			$restoredNodes = array();
			if( count( $rows ) > 0 ) {
				foreach ( $rows as $row )
				{
					$restoredNodes = array_merge($restoredNodes, restoreSubtree( $row['contentobject_id'], $db ));
				}				
			}
        }
        else
        {
            return $Module->handleError( eZError::KERNEL_ACCESS_DENIED, 'kernel' );
        }
	}
}

$tpl = eZTemplate::factory();
$tpl->setVariable( 'view_parameters', $viewParameters );
if ( count($restoredNodes) > 0 )
{
	$tpl->setVariable('restored_nodes', $restoredNodes );
}

$Result = array();
$Result['content'] = $tpl->fetch( 'design:restoretrash/trash.tpl' );
$Result['path'] = array( array( 'text' => ezpI18n::tr( 'kernel/content', 'Trash' ),
                                'url' => false ) );


function restoreSubtree( $objectID, $db )
{	
	$query = sprintf( 'SELECT path_string FROM ezcontentobject_trash WHERE contentobject_id= "%d"', $objectID );
	$rows = $db->arrayQuery( $query );
	if( count( $rows ) > 0 ) 
	{
		$pathString = $rows[0]['path_string'];
		//$cli->output( 'Restoring top node from trash' );
	}
	else {
		return array();
	}
	
	$query = sprintf( 'SELECT * FROM ezcontentobject_trash WHERE path_string LIKE "%s%%" ORDER BY depth', $pathString );
	$trashList = $db->arrayQuery( $query );
	
	$restoreAttributes = array( 'is_hidden', 'is_invisible', 'priority', 'sort_field', 'sort_order' );
	$checkedParents = array();
	$restoredNodes = array();
	$nodeMappings = array( 'a' => 'b' );
	foreach( $trashList as $trashItem ) 
	{
		$objectID     = $trashItem['contentobject_id'];
		$parentNodeID = ($nodeMappings && $nodeMappings[$trashItem['parent_node_id']]) ? $nodeMappings[$trashItem['parent_node_id']] : $trashItem['parent_node_id'];
		$orgNodeID    = $trashItem['node_id'];

		// Check if object exists
		$object = eZContentObject::fetch( $objectID );
		if ( !is_object( $object ) ) 
		{
			//$cli->error( sprintf( 'Object %d does not exist', $objectID ));
			continue;
		}
		//$cli->output( sprintf( 'Restoring object %d, "%s"', $objectID, $object->Name ));

		// Check whether object is archived indeed
		if ( $object->attribute( 'status' ) != eZContentObject::STATUS_ARCHIVED )
		{
			//$cli->error( sprintf( 'Object %d is not archived', $objectID ));
			continue;
		}

		// Check if parent node exists
		if( !array_key_exists( $parentNodeID, $checkedParents ))
		{
			$parentNode = eZContentObjectTreeNode::fetch( $parentNodeID );			
			$checkedParents[$parentNodeID] = is_object( $parentNode );
		}
		if( !$checkedParents[$parentNodeID] ) 
		{
			//$cli->error( sprintf( 'Parent node for object %d does not exist', $objectID ));
			continue;
		}

		$version = $object->attribute( 'current' );
		$location = eZNodeAssignment::fetch( $object->ID, $version->Version, $parentNodeID );

		/*$opCode = $location->attribute( 'op_code' );
		$opCode &= ~1;
		// We only include assignments which create or nops.
		if ( !$opCode == eZNodeAssignment::OP_CODE_CREATE_NOP && !$opCode == eZNodeAssignment::OP_CODE_NOP ) {
			//$cli->error( sprintf( 'Object %d can not be restored', $object->ID ));
			continue;
		}

		$selectedNodeID = $location->attribute( 'parent_node' );
*/
		$db->begin();

		// Remove all existing assignments, only our new ones should be present.
		foreach ( $version->attribute( 'node_assignments' ) as $assignment )
		{
			$assignment->purge();
		}

		$version->assignToNode( $parentNodeID, true );

		$object->setAttribute( 'status', eZContentObject::STATUS_DRAFT );
		$object->store();
		$version->setAttribute( 'status', eZContentObjectVersion::STATUS_DRAFT );
		$version->store();

		$user = eZUser::fetch( $version->CreatorID );
		$operationResult = eZOperationHandler::execute( 'content', 'publish', array( 'object_id' => $objectID,
																					 'version' => $version->attribute( 'version' ) ) );
		$objectID = $object->attribute( 'id' );
		$object = eZContentObject::fetch( $objectID );
		$mainNodeID = $object->attribute( 'main_node_id' );

		// Restore original node number
		changeNodeID( $mainNodeID, $orgNodeID, $db );
		$nodeMappings[$orgNodeID] = $mainNodeID;
		//$nodesMapping = array_merge( $nodesMapping, array( $mainNodeID => $orgNodeID ) );

		// Restore other attributes
		$node = eZContentObjectTreeNode::fetch( $mainNodeID );
		foreach( $restoreAttributes as $attr )
		{
			$node->setAttribute( $attr, $trashItem[ $attr ] );
		}
		$node->store();

		eZContentObjectTrashNode::purgeForObject( $objectID  );

		if ( $object->attribute( 'contentclass_id' ) == $userClassID )
		{
			eZUser::purgeUserCacheByUserId( $object->attribute( 'id' ) );
		}
		eZContentObject::fixReverseRelations( $objectID, 'restore' );		
		$db->commit();
		$restoredNodes[] = $node;
		$node->updateSubTreePath();

		//$cli->output( sprintf( 'Restored at node %d', $orgNodeID ));
	}
	return $restoredNodes;
}

function changeNodeID( $fromID, $toID, $db )
{    
    
	$query = 'UPDATE `ezcontentobject_trash` SET `path_string`= REPLACE( `path_string`, "'.$toID.'", "'.$fromID.'" ), `parent_node_id`="'.$fromID.'" WHERE path_string LIKE "%'.$toID.'%"';
    $db->query( $query ); 
        
}

?>
