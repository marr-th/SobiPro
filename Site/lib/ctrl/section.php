<?php
/**
 * @version: $Id$
 * @package: SobiPro Library
 * @author
 * Name: Sigrid Suski & Radek Suski, Sigsiu.NET GmbH
 * Email: sobi[at]sigsiu.net
 * Url: http://www.Sigsiu.NET
 * @copyright Copyright (C) 2006 - 2013 Sigsiu.NET GmbH (http://www.sigsiu.net). All rights reserved.
 * @license GNU/LGPL Version 3
 * This program is free software: you can redistribute it and/or modify it under the terms of the GNU Lesser General Public License version 3 as published by the Free Software Foundation, and under the additional terms according section 7 of GPL v3.
 * See http://www.gnu.org/licenses/lgpl.html and http://sobipro.sigsiu.net/licenses.
 * This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.
 * $Date$
 * $Revision$
 * $Author$
 * $HeadURL$
 */

defined( 'SOBIPRO' ) || exit( 'Restricted access' );

SPLoader::loadController( 'controller' );

/**
 * @author Radek Suski
 * @version 1.0
 * @created 10-Jan-2009 5:08:52 PM
 */
class SPSectionCtrl extends SPController
{
	/**
	 * @var string
	 */
	protected $_defTask = 'view';
	/**
	 * @var string
	 */
	protected $_type = 'section';

	/**
	 */
	protected function view()
	{
		/* determine template package */
		$tplPackage = Sobi::Cfg( 'section.template', SPC::DEFAULT_TEMPLATE );
		Sobi::ReturnPoint();

		/* load template config */
		$this->template();
		$this->tplCfg( $tplPackage );

		/* get limits - if defined in template config - otherwise from the section config */
		$eLimit = $this->tKey( $this->template, 'entries_limit', Sobi::Cfg( 'list.entries_limit', 2 ) );
		$eInLine = $this->tKey( $this->template, 'entries_in_line', Sobi::Cfg( 'list.entries_in_line', 2 ) );
		$cInLine = $this->tKey( $this->template, 'categories_in_line', Sobi::Cfg( 'list.categories_in_line', 2 ) );
		$cLim = $this->tKey( $this->template, 'categories_limit', -1 );
		$entriesRecursive = $this->tKey( $this->template, 'entries_recursive', Sobi::Cfg( 'list.entries_recursive', false ) );

		/* get the site to display */
		$site = SPRequest::int( 'site', 1 );
		$eLimStart = ( ( $site - 1 ) * $eLimit );

		/* get the right ordering */
		$eOrder = $this->parseOrdering( 'entries', 'eorder', $this->tKey( $this->template, 'entries_ordering', Sobi::Cfg( 'list.entries_ordering', 'name.asc' ) ) );
		$cOrder = $this->parseOrdering( 'categories', 'corder', $this->tKey( $this->template, 'categories_ordering', Sobi::Cfg( 'list.categories_ordering', 'name.asc' ) ) );

		$orderings = array( 'entries' => $eOrder, 'categories' => $cOrder );
		/* get entries */
		$eCount = count( $this->getEntries( $eOrder, 0, 0, true, null, $entriesRecursive ) );
		$entries = $this->getEntries( $eOrder, $eLimit, $eLimStart, false, null, $entriesRecursive );
		$categories = array();
		if ( $cLim ) {
			$categories = $this->getCats( $cOrder, $cLim );
		}

		/* create page navigation */
		$url = array( 'sid' => SPRequest::sid(), 'title' => Sobi::Cfg( 'sef.alias', true ) ? $this->_model->get( 'nid' ) : $this->_model->get( 'name' ) );
		if ( SPRequest::cmd( 'sptpl' ) ) {
			$url[ 'sptpl' ] = SPRequest::cmd( 'sptpl' );
		}
		$pnc = SPLoader::loadClass( 'helpers.pagenav_' . $this->tKey( $this->template, 'template_type', 'xslt' ) );
		/* @var SPPageNavXSLT $pn */
		$pn = new $pnc( $eLimit, $eCount, $site, array( 'sid' => SPRequest::sid(), 'title' => Sobi::Cfg( 'sef.alias', true ) ? $this->_model->get( 'nid' ) : $this->_model->get( 'name' ) ) );

		/* handle meta data */
		SPFactory::header()->objMeta( $this->_model );

		/* add pathway */
		SPFactory::mainframe()->addObjToPathway( $this->_model, array( ceil( $eCount / $eLimit ), $site ) );

		$this->_model->countVisit();
		/* get view class */
//		$class = SPLoader::loadView( $this->_type );
		$view = SPFactory::View( $this->_type );
//		$view = new $class( $this->template );
		$view->assign( $eLimit, '$eLimit' )
				->assign( $eLimStart, '$eLimStart' )
				->assign( $eCount, '$eCount' )
				->assign( $cInLine, '$cInLine' )
				->assign( $eInLine, '$eInLine' )
				->assign( $this->_task, 'task' )
				->assign( $this->_model, $this->_type )
				->setConfig( $this->_tCfg, $this->template )
				->setTemplate( $tplPackage . '.' . $this->templateType . '.' . $this->template )
				->assign( $categories, 'categories' )
				->assign( $pn->get(), 'navigation' )
				->assign( SPFactory::user()->getCurrent(), 'visitor' )
				->assign( $entries, 'entries' )
				->assign( $orderings, 'orderings' );
		Sobi::Trigger( $this->name(), 'View', array( &$view ) );
		$view->display( $this->_type );
	}

	/**
	 * @param $cOrder
	 * @param int $cLim
	 * @internal param string $eOrder
	 * @internal param int $eLimit
	 * @internal param int $eLimStart
	 * @return array
	 */
	public function getCats( $cOrder, $cLim = 0 )
	{
		$categories = array();
		$cOrder = trim( $cOrder );
		$cLim = $cLim > 0 ? $cLim : 0;
		if ( $this->_model->getChilds( 'category' ) ) {
			/* var SPDb $db */
			$db = SPFactory::db();
			$oPrefix = null;

			/* load needed definitions */
			SPLoader::loadClass( 'models.dbobject' );
			$conditions = array();

			switch ( $cOrder ) {
				case 'name.asc':
				case 'name.desc':
					$table = $db->join( array(
							array( 'table' => 'spdb_language', 'as' => 'splang', 'key' => 'id' ),
							array( 'table' => 'spdb_object', 'as' => 'spo', 'key' => 'id' )
					) );
					$oPrefix = 'spo.';
					$conditions[ 'spo.oType' ] = 'category';
					$conditions[ 'splang.sKey' ] = 'name';
					$conditions[ 'splang.language' ] = array( Sobi::Lang( false ), Sobi::DefLang(), 'en-GB' );
					if ( strstr( $cOrder, '.' ) ) {
						$cOrder = explode( '.', $cOrder );
						$cOrder = 'sValue.' . $cOrder[ 1 ];
					}
					break;
				case 'position.asc':
				case 'position.desc':
					$table = $db->join( array(
							array( 'table' => 'spdb_relations', 'as' => 'sprl', 'key' => 'id' ),
							array( 'table' => 'spdb_object', 'as' => 'spo', 'key' => 'id' )
					) );
					$conditions[ 'spo.oType' ] = 'category';
					$oPrefix = 'spo.';
					break;
				case 'counter.asc':
				case 'counter.desc':
					$table = $db->join( array(
							array( 'table' => 'spdb_counter', 'as' => 'spcounter', 'key' => 'sid' ),
							array( 'table' => 'spdb_object', 'as' => 'spo', 'key' => 'id' )
					) );
					$oPrefix = 'spo.';
					$conditions[ 'spo.oType' ] = 'category';
					if ( strstr( $cOrder, '.' ) ) {
						$cOrder = explode( '.', $cOrder );
						$cOrder = 'spcounter.counter.' . $cOrder[ 1 ];
					}
					break;
				default:
					$table = 'spdb_object';
					break;
			}

			/* check user permissions for the visibility */
			if ( Sobi::My( 'id' ) ) {
				if ( !( Sobi::Can( 'category.access.*' ) ) ) {
					if ( Sobi::Can( 'category.access.unapproved_own' ) ) {
						$conditions[ ] = $db->argsOr( array( 'approved' => '1', 'owner' => Sobi::My( 'id' ) ) );
					}
					else {
						$conditions[ $oPrefix . 'approved' ] = '1';
					}
				}
				if ( !( Sobi::Can( 'category.access.unpublished' ) ) ) {
					if ( Sobi::Can( 'category.access.unpublished_own' ) ) {
						$conditions[ ] = $db->argsOr( array( 'state' => '1', 'owner' => Sobi::My( 'id' ) ) );
					}
					else {
						$conditions[ $oPrefix . 'state' ] = '1';
					}
				}
				if ( !( Sobi::Can( 'category.access.*' ) ) ) {
					if ( Sobi::Can( 'category.access.expired_own' ) ) {
						$conditions[ ] = $db->argsOr( array( '@VALID' => $db->valid( $oPrefix . 'validUntil', $oPrefix . 'validSince' ), 'owner' => Sobi::My( 'id' ) ) );
					}
					else {
						$conditions[ 'state' ] = '1';
						$conditions[ '@VALID' ] = $db->valid( $oPrefix . 'validUntil', $oPrefix . 'validSince' );
					}
				}
			}
			else {
				$conditions = array_merge( $conditions, array( $oPrefix . 'state' => '1', $oPrefix . 'approved' => '1', '@VALID' => $db->valid( $oPrefix . 'validUntil', $oPrefix . 'validSince' ) ) );
			}
			$conditions[ $oPrefix . 'id' ] = $this->_model->getChilds( 'category' );
			try {
				$results = $db
						->select( $oPrefix . 'id', $table, $conditions, $cOrder, $cLim, 0, true )
						->loadResultArray();
			} catch ( SPException $x ) {
				Sobi::Error( $this->name(), SPLang::e( 'DB_REPORTS_ERR', $x->getMessage() ), SPC::WARNING, 0, __LINE__, __FILE__ );
			}
			Sobi::Trigger( $this->name(), 'AfterGetCategories', array( &$results ) );
			if ( $results && count( $results ) ) {
				foreach ( $results as $i => $cid ) {
					$categories[ $i ] = $cid; // new $cClass();
					//$categories[ $i ]->init( $cid );
				}
			}
		}
		return $categories;
	}

	protected function userPermissionsQuery( &$conditions, $oPrefix = null )
	{
		$db =& SPFactory::db();
		if ( !( Sobi::Can( 'entry.access.*' ) ) ) {
			if ( Sobi::Can( 'entry.access.unpublished_own' ) ) {
				$conditions[ ] = $db->argsOr( array( $oPrefix . 'state' => '1', $oPrefix . 'owner' => Sobi::My( 'id' ) ) );
				if ( !( Sobi::Can( 'entry.access.unapproved_own' ) || Sobi::Can( 'entry.access.unapproved_any' ) ) ) {
					$conditions[ $oPrefix . 'approved' ] = '1';
				}
			}
			elseif ( !( Sobi::Can( 'entry.access.unpublished_any' ) ) ) {
				$conditions[ $oPrefix . 'state' ] = '1';
			}
		}
		if ( !( Sobi::Can( 'entry.access.*' ) ) ) {
			// @todo: expired permission
			if ( Sobi::Can( 'entry.access.expired_own' ) ) {
				$conditions[ ] = $db->argsOr( array( '@VALID' => $db->valid( $oPrefix . 'validUntil', $oPrefix . 'validSince' ), 'owner' => Sobi::My( 'id' ) ) );
			}
			else {
				// conflicts with "entry.access.unpublished_own" See #521
				//$conditions[ 'state' ] = '1';
				$conditions[ '@VALID' ] = $db->valid( $oPrefix . 'validUntil', $oPrefix . 'validSince' );
			}
		}
		return $conditions;
	}

	/**
	 * @param string $eOrder
	 * @param int $eLimit
	 * @param int $eLimStart
	 * @param bool $count
	 * @param array $conditions
	 * @param bool $entriesRecursive
	 * @param int $pid
	 * @return array
	 */
	public function getEntries( $eOrder, $eLimit = null, $eLimStart = null, $count = false, $conditions = array(), $entriesRecursive = false, $pid = 0 )
	{
		/* var SPDb $db */
		$db = SPFactory::db();
		$entries = array();
		$eDir = 'asc';
		$oPrefix = null;
		$conditions = is_array( $conditions ) ? $conditions : array();

		/* get the ordering and the direction */
		if ( strstr( $eOrder, '.' ) ) {
			$eOr = explode( '.', $eOrder );
			$eOrder = array_shift( $eOr );
			$eDir = implode( '.', $eOr );
		}
		$pid = $pid ? $pid : SPRequest::sid();
		/* if sort by name, then sort by the name field */
		if ( $eOrder == 'name' ) {
			$eOrder = SPFactory::config()
					->nameField()
					->get( 'fid' );
		}
		if ( $entriesRecursive ) {
			$pids = $this->_model->getChilds( 'category', true );
			if ( is_array( $pids ) ) {
				$pids = array_keys( $pids );
			}
			$pids[ ] = SPRequest::sid();
			$conditions[ 'sprl.pid' ] = $pids;
		}
		else {
			$conditions[ 'sprl.pid' ] = $pid;
		}
		if ( $pid == -1 ) {
			unset( $conditions[ 'sprl.pid' ] );
		}

		/* sort by field */
		if ( strstr( $eOrder, 'field_' ) ) {
			static $field = null;
			$specificMethod = false;
			if ( !$field ) {
				try {
					$fType = $db
							->select( 'fieldType', 'spdb_field', array( 'nid' => $eOrder, 'section' => Sobi::Section() ) )
							->loadResult();
				} catch ( SPException $x ) {
					Sobi::Error( $this->name(), SPLang::e( 'CANNOT_DETERMINE_FIELD_TYPE', $x->getMessage() ), SPC::WARNING, 0, __LINE__, __FILE__ );
				}
				if ( $fType ) {
					$field = SPLoader::loadClass( 'opt.fields.' . $fType );
				}
			}
			if ( $field && method_exists( $field, 'customOrdering' ) ) {
				$table = null;
				$oPrefix = null;
				$specificMethod = call_user_func_array( array( $field, 'customOrdering' ), array( &$table, &$conditions, &$oPrefix, &$eOrder, &$eDir ) );
			}
			elseif ( $field && method_exists( $field, 'sortBy' ) ) {
				$table = null;
				$oPrefix = null;
				$specificMethod = call_user_func_array( array( $field, 'sortBy' ), array( &$table, &$conditions, &$oPrefix, &$eOrder, &$eDir ) );
			}
			if ( !$specificMethod ) {
				$table = $db->join(
						array(
								array( 'table' => 'spdb_field', 'as' => 'fdef', 'key' => 'fid' ),
								array( 'table' => 'spdb_field_data', 'as' => 'fdata', 'key' => 'fid' ),
								array( 'table' => 'spdb_object', 'as' => 'spo', 'key' => array( 'fdata.sid', 'spo.id' ) ),
								array( 'table' => 'spdb_relations', 'as' => 'sprl', 'key' => array( 'fdata.sid', 'sprl.id' ) ),
						)
				);
				$oPrefix = 'spo.';
				$conditions[ 'spo.oType' ] = 'entry';
				$conditions[ 'fdef.nid' ] = $eOrder;
				$eOrder = 'baseData.' . $eDir;
			}
		}
		elseif ( strstr( $eOrder, 'counter' ) ) {
			$table = $db->join( array(
					array( 'table' => 'spdb_object', 'as' => 'spo', 'key' => 'id' ),
					array( 'table' => 'spdb_relations', 'as' => 'sprl', 'key' => array( 'spo.id', 'sprl.id' ) ),
					array( 'table' => 'spdb_counter', 'as' => 'spcounter', 'key' => array( 'spo.id', 'spcounter.sid' ) ),
			) );
			$oPrefix = 'spo.';
			$conditions[ 'spo.oType' ] = 'entry';
			if ( strstr( $eOrder, '.' ) ) {
				$cOrder = explode( '.', $eOrder );
				$eOrder = 'spcounter.counter.' . $cOrder[ 1 ];
			}
			else {
				$eOrder = 'spcounter.counter.desc';
			}
		}
		else {
			$table = $db->join( array(
					array( 'table' => 'spdb_relations', 'as' => 'sprl', 'key' => 'id' ),
					array( 'table' => 'spdb_object', 'as' => 'spo', 'key' => 'id' )
			) );
			$conditions[ 'spo.oType' ] = 'entry';
			$eOrder = $eOrder . '.' . $eDir;
			$oPrefix = 'spo.';
			if ( strstr( $eOrder, 'valid' ) ) {
				$eOrder = $oPrefix . $eOrder;
			}
		}

		/* check user permissions for the visibility */
		if ( Sobi::My( 'id' ) ) {
			$this->userPermissionsQuery( $conditions, $oPrefix );
			if ( isset( $conditions[ $oPrefix . 'state' ] ) && $conditions[ $oPrefix . 'state' ] ) {
				$conditions[ 'sprl.copy' ] = 0;
			}
		}
		else {
			$conditions = array_merge( $conditions, array( $oPrefix . 'state' => '1', '@VALID' => $db->valid( $oPrefix . 'validUntil', $oPrefix . 'validSince' ) ) );
			$conditions[ 'sprl.copy' ] = '0';
		}
		try {
			$results = $db
					->select( $oPrefix . 'id', $table, $conditions, $eOrder, $eLimit, $eLimStart, true )
					->loadResultArray();
		} catch ( SPException $x ) {
			Sobi::Error( $this->name(), SPLang::e( 'DB_REPORTS_ERR', $x->getMessage() ), SPC::WARNING, 0, __LINE__, __FILE__ );
		}
		Sobi::Trigger( $this->name(), 'AfterGetEntries', array( &$results, $count ) );
		if ( count( $results ) && !$count ) {
			foreach ( $results as $i => $sid ) {
				// it needs too much memory moving the object creation to the view
				//$entries[ $i ] = SPFactory::Entry( $sid );
				$entries[ $i ] = $sid;
			}
		}
		if ( $count ) {
			Sobi::SetUserData( 'currently-displayed-entries', $results );
			return $results;
		}
		return $entries;
	}
}
