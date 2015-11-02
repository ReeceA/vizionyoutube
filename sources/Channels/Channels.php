<?php

namespace IPS\viziongm;

/* To prevent PHP errors (extending class does not exist) revealing path */
use IPS\Login\Exception;

if ( !defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

class _Channels extends \IPS\Patterns\ActiveRecord
{

	/**
	 * @brief   Multiton Store
	 */
	protected static $multitons = array();

	/**
	 * @brief    Database table
	 */
	public static $databaseTable = 'vizionyoutube_watchlist';

	/**
	 * @brief    Database ID Fields
	 */
	protected static $databaseIdFields = array(
		'id',
		'yt_channel_id',
		'yt_username'

	);

	/**
	 * @brief   Multiple Channels Identifier
	 */
	protected static $hasMultiple = FALSE;

	/**
	 * @brief   Data Store
	 */
	protected static $data = array();

	public static function load( $username )
	{
		// First, lets get the data
		try
		{
			self::$data = parent::load( $username, 'yt_username' );

		}
		catch ( \OutOfRangeException $e )
		{
			return FALSE;
		}


		// We can set the multiple identifier in advance by checking if any data
		// is stored.
		self::$hasMultiple =
			( isset( self::$data->yt_channels_multiple_data ) ) ? (bool) self::$data->yt_channels_multiple : FALSE;

		// Yep! We have our channel ID set, lets go ahead and return our payload
		return self::$data;
	}

	/**
	 * @param $username
	 * @see https://www.googleapis.com/youtube/v3/channels?key=AIzaSyCm8wRaiyRa1RLalvIayxBOC1k3zVkalF8&forUsername=FenomenStars&part=id
	 * @return bool|static
	 * @throws InvalidAPIKey
	 * @throws NoAPIKey
	 */
	public static function fetchChannelDataByUsername( $username )
	{
		// Has an API key been supplied?
		if ( ( $api_key = \IPS\Settings::i()->vizionyoutube_api_key ) === NULL )
		{
			throw new NoAPIKey( 'NO_API_KEY' );
		}

		// Lets load our data, we can skip our version and cut to the parent
		$data = parent::load( $username, 'yt_username' );

		// Yup, lets get to it and build our URL
		$url = \IPS\Http\Url::external( 'https://www.googleapis.com/youtube/v3/channels' )->setQueryString(
			'key', $api_key
		)->setQueryString( 'forUsername', $username )->setQueryString( 'part', 'id' );

		// Init our curl
		$curl = new \IPS\Http\Request\Curl( $url );

		// Get our response
		$response = $curl->get();

		// Close the curl just cause I'm tidy like that.
		unset( $curl );

		// Attempt tp pull channel data from cURL response
		$channels = self::parseResponse( $response );

		if ( is_array( $channels ) AND !empty( $channels ) )
		{
			// Yay we got some channels, do we have more than one though?
			if ( count( $channels ) > 1 )
			{
				// has Multiple!
				self::$hasMultiple = TRUE;
				$data->yt_channel_multiple = 1;
				$data->yt_channel_multiple_data = $channels;
				$data->save();

				// Return data for daisy chaining
				return $data;
			}

			$data->yt_channel_multiple = 0;
			$data->yt_channel_multiple_data = NULL;
			$data->yt_channel_id =
				$channels[0]['id']; // The purpose of doing this is to really cut the need to use json decode so frequently. Most users only have one channel

			// Save and return for daisy chaining
			$data->save();

			return $data;

		}

		// Username has no channels or username is not a YouTube member.
		return FALSE;

	}

	/**
	 * Is Multiple?
	 *
	 * @return mixed
	 */
	public function get_yt_channel_multiple()
	{
		return self::$data->yt_channel_multiple;
	}

	/**
	 * Set Multiple
	 *
	 * @param $value
	 */
	public function set_yt_channel_multiple( $value )
	{
		self::$data->yt_channel_multiple = $value;
	}

	/**
	 * Get Channel Data
	 *
	 * @return mixed
	 */
	public function get_yt_channel_multiple_data()
	{
		return self::$data->yt_channel_multiple_data;
	}

	/**
	 * Set Channel Data
	 *
	 * @param $value
	 */
	public function set_yt_channel_multiple_data( $value )
	{
		self::$data->yt_channel_multiple_data = $value;
	}

	/**
	 * Used to determine whether
	 *
	 * @param $response
	 * @return bool|static
	 * @throws InvalidAPIKey
	 */
	public static function parseResponse( $response )
	{

		// JSON?
		if ( is_string( $response ) )
		{
			$response = json_decode( $response, TRUE );
		}


		// Are there any errors?
		if ( isset( $response['error']['errors'] ) )
		{
			// Yes there is, is it due to a bad key though?
			$error = $response['error']['errors']; // F*** typing that everytime.

			if ( $error['reason'] == 'keyInvalid' )
			{
				// Yup, bad key.
				throw new InvalidAPIKey( 'INVALID_API_KEY' );
			}

			// Return FALSE declaring that the response is not OK to proceed with.
			return FALSE;
		}

		// Guess not, we're all good to return then.....  or are we ;)
		return ( isset( $response['items'] ) ) ? $response['items'] : FALSE;

	}
}

class NoAPIKey extends \Exception
{
}

class InvalidAPIKey extends \Exception
{
}
