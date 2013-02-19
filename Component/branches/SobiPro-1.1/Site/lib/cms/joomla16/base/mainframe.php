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
require_once dirname( __FILE__ ) . '/../../joomla_common/base/mainframe.php';
/**
 * Interface between SobiPro and the used CMS
 * @author Radek Suski
 * @version 1.0
 * @created 10-Jan-2009 5:50:43 PM
 */
class SPJ16MainFrame extends SPJoomlaMainFrame implements SPMainframeInterface
{
	/**
	 * Gets basic data from the CMS (e.g Joomla) and stores in the #SPConfig instance
	 */
	public function getBasicCfg()
	{
		parent::getBasicCfg();
		if ( defined( 'SOBIPRO_ADM' ) ) {
			SPFactory::config()->change( 'adm_img_folder_live', Sobi::FixPath( JURI::root() . DS . SOBI_ADM_FOLDER . DS . 'templates' . DS . JFactory::getApplication()->getTemplate() . '/images/admin' ), 'general' );
		}
	}

	/**
	 * @param string $title
	 * @param bool $forceAdd
	 * @return void
	 */
	public function setTitle( $title, $forceAdd = false )
	{
		if ( defined( 'SOBIPRO_ADM' ) ) {
			JToolbarHelper::title( 'SobiPro: ' . $title );
		}
		parent::setTitle( $title, $forceAdd );
	}

	/**
	 * @param array $head
	 * @return bool
	 */
	public function addHead( $head )
	{
		if ( strlen( SPRequest::cmd( 'format' ) ) ) {
			return true;
		}
		/** @var JDocument $document */
		$document = JFactory::getDocument();
		$c = 0;
		if ( count( $head ) ) {
			$document->addCustomTag( "\n\t<!--  SobiPro Head Tags Output  -->\n" );
			$document->addCustomTag( "\n\t<script type=\"text/javascript\">/*\n<![CDATA[*/ \n\tvar SobiProUrl = '" . Sobi::FixPath( self::Url( array( 'task' => '%task%' ), true, false, true ) ) . "'; \n\tvar SobiProSection = " . ( Sobi::Section() ? Sobi::Section() : 0 ) . "; \n\tvar SPLiveSite = '" . Sobi::Cfg( 'live_site' ) . "'; \n/*]]>*/\n</script>\n" );
			if ( defined( 'SOBI_ADM_PATH' ) ) {
				$document->addCustomTag( "\n\t<script type=\"text/javascript\">/* <![CDATA[ */ \n\tvar SobiProAdmUrl = '" . Sobi::FixPath( Sobi::Cfg( 'live_site' ) . SOBI_ADM_FOLDER . '/' . self::Url( array( 'task' => '%task%' ), true, false ) ) . "'; \n/* ]]> */</script>\n" );
			}
			foreach ( $head as $type => $code ) {
				switch ( $type ) {
					default:
						{
						if ( count( $code ) ) {
							foreach ( $code as $html ) {
								++$c;
								$document->addCustomTag( $html );
							}
						}
						break;
						}
					case 'robots' :
					case 'author':
					{
						$document->setMetaData( $type, implode( ', ', $code ) );
//						$document->setHeadData( array( $type => implode( ', ', $code ) ) );
						break;
					}
					case 'keywords':
					{
						$metaKeys = trim( implode( ', ', $code ) );
						if ( Sobi::Cfg( 'meta.keys_append', true ) ) {
							$metaKeys .= Sobi::Cfg( 'string.meta_keys_separator', ',' ) . $document->getMetaData( 'keywords' );
						}
						$metaKeys = explode( Sobi::Cfg( 'string.meta_keys_separator', ',' ), $metaKeys );
						if ( count( $metaKeys ) ) {
							foreach ( $metaKeys as $i => $p ) {
								if ( strlen( trim( $p ) ) ) {
									$metaKeys[ $i ] = trim( $p );
								}
								else {
									unset( $metaKeys[ $i ] );
								}
							}
							$metaKeys = implode( ', ', $metaKeys );
						}
						else {
							$metaKeys = null;
						}
						$document->setMetadata( 'keywords', $metaKeys );
						break;
					}
					case 'description':
					{
						$metaDesc = implode( Sobi::Cfg( 'string.meta_desc_separator', ' ' ), $code );
						if ( strlen( $metaDesc ) ) {
							if ( Sobi::Cfg( 'meta.desc_append', true ) ) {
								$metaDesc .= $this->getMetaDescription( $document );
							}
							$metaDesc = explode( ' ', $metaDesc );
							if ( count( $metaDesc ) ) {
								foreach ( $metaDesc as $i => $p ) {
									if ( strlen( trim( $p ) ) ) {
										$metaDesc[ $i ] = trim( $p );
									}
									else {
										unset( $metaDesc[ $i ] );
									}
								}
								$metaDesc = implode( ' ', $metaDesc );
							}
							else {
								$metaDesc = null;
							}
							$document->setDescription( $metaDesc );
						}
						break;
					}
				}
			}
			$jsUrl = Sobi::FixPath( self::Url( array( 'task' => 'txt.js', 'format' => 'json' ), true, false, false ) );
			$document->addCustomTag( "\n\t<script type=\"text/javascript\" src=\"" . str_replace( '&', '&amp;', $jsUrl ) . "\"></script>\n" );
			$c++;
			$document->addCustomTag( "\n\t<!--  SobiPro ({$c}) Head Tags Output -->\n" );
		}
	}

	protected function getMetaDescription( $document )
	{
		return $document->get( 'description' );
	}

	protected function JConfigValue( $value )
	{
		$value = str_replace( 'config.', null, $value );
		return JFactory::getConfig()->get( $value );
	}

	/**
	 * @return SPJoomlaMainFrame
	 */
	public static function & getInstance()
	{
		static $mf = false;
		if ( !( $mf ) || !( $mf instanceof self ) ) {
			$mf = new self();
		}
		return $mf;
	}

	/**
	 * Method to determine a hash for anti-spoofing variable names
	 * @return string
	 */
	public function token()
	{
		return JSession::getFormToken();
	}

	/**
	 * Checks for a form token in the request.
	 * @param string $method
	 * @return boolean
	 */
	public function checkToken( $method = 'post' )
	{
		if ( Sobi::Cfg( 'security.token', true ) ) {
			return JSession::checkToken( $method );
		}
		else {
			return true;
		}
	}

}
