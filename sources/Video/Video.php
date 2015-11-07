<?php

namespace IPS\vizionyoutube;

/* To prevent PHP errors (extending class does not exist) revealing path */

if ( !defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

class _Video extends \IPS\Patterns\ActiveRecord
{
	/**
	 * @brief   Multiton Store
	 */
	protected static $multitons = array();

	/**
	 * @brief    Database table
	 */
	public static $databaseTable = 'vizionyoutube_videos';

	/**
	 * @brief    Database ID Fields
	 */
	protected static $databaseIdFields = array(
		'id',
		'yt_video_id'
	);

	/**
	 * @param null   $id
	 * @param string $column
	 */
	public function __construct( $id = NULL, $column = 'yt_video_id' )
	{
		if ( !in_array( $column, static::$databaseIdFields ) )
		{
			throw new \InvalidArgumentException(
				"Illegal column used ($column). Must be one of:  " . implode( ', ', static::$databaseIdFields )
			);
		}

		parent::__construct();

		parent::load($id, $column);

	}



}