<?php

namespace IPS\vizionyoutube;

/* To prevent PHP errors (extending class does not exist) revealing path */

if ( !defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

class _Watchlist extends \IPS\Patterns\ActiveRecord
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
	 * @return mixed
	 * @throws NoAPIKey
	 */
	public function hasApiKey()
	{
		// Has an API key been supplied?
		if ( ( $api_key = \IPS\Settings::i()->vizionyoutube_api_key ) === NULL )
		{
			throw new NoAPIKey( 'NO_API_KEY' );
		}

		return $api_key;
	}

	/**
	 * @see https://www.googleapis.com/youtube/v3/channels?key=AIzaSyCm8wRaiyRa1RLalvIayxBOC1k3zVkalF8&forUsername=FenomenStars&part=id
	 * @return bool|static
	 * @throws InvalidAPIKey
	 * @throws NoAPIKey
	 * @throws NoSuchUser
	 */
	public function fetchChannelID()
	{
		$api_key = $this->hasApiKey();

		// Do we have a username?
		if ( $this->_data['yt_username'] === NULL )
		{
			throw new NoSuchUser( 'fetchChannelData() called without loading a username via load() first' );
		}

		// Do we have a channel(s)?
		if ( isset( $this->_data['yt_channel_id'] ) )
		{
			// *for readability*:
			// If we already checked within the last 24 hours, can we return early?
			if ( !( time() - $this->_data['last_checked'] ) > 86400 )
			{
				// Yes we can, yay for saving resources!
				return $this->prepareChannelIDs();
			}

			// Nup, we have to check it, follow through.........
		}

		// Build our API URL
		$url = \IPS\Http\Url::external( 'https://www.googleapis.com/youtube/v3/channels' )->setQueryString(
			'key', $api_key
		)->setQueryString( 'forUsername', $this->_data['username'] )->setQueryString( 'part', 'id' );

		// Init our curl
		$curl = new \IPS\Http\Request\Curl( $url );
		// Get our response
		$response = $curl->get();
		// Close the curl just cause I'm tidy like that.
		unset( $curl );
		// Attempt tp pull channel data from cURL response
		$channels = $this->parseResponse( $response );

		if ( is_array( $channels ) AND !empty( $channels ) )
		{
			// Yay we got some channels, do we have more than one though?
			if ( count( $channels ) > 1 )
			{
				// has Multiple!
				$this->_data['yt_channel_multiple'] = 1;
				$this->_data['yt_channel_multiple_data'] = $channels;
				$this->save();

				// Return data for daisy chaining
				return $this->_data;
			}

			$this->_data['yt_channel_multiple'] = 0;
			$this->_data['yt_channel_multiple_data'] = NULL;
			$this->_data['yt_channel_id'] =
				$channels[0]['id']; // Easier to check if this is set first, then decode "multiple data" every time.

			$this->save();

			// Return for daisy chaining
			return $this->_data;

		}

		// Username has no channels or username is not a YouTube member.
		return FALSE;

	}

	/**
	 * Used to determine whether the response is appropriate to continue with
	 *
	 * @param $response
	 * @return bool|static
	 * @throws InvalidAPIKey
	 */
	public function parseResponse( $response )
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

	/**
	 * @param           $username
	 * @param bool|TRUE $verifyFirst
	 * @return bool
	 * @throws NoAPIKey
	 */
	public function addUsernameToWatchList( $username, $verifyFirst = TRUE )
	{

		if ( $verifyFirst )
		{
			// To verify, we need the API key set
			// A "NoAPIKey" exception will be thrown if it's not set.
			// We'll let this go uncaught and follow through the stack
			$api_key = $this->hasApiKey();

			// This will throw an exception if it fails, or return false if it fails.
			// If it's successful, then it contains
			if ( !$verified = $this->verifyUsername( $username ) )
			{
				throw new \OutOfRangeException( 'That username does not exist, or does not have any channels' );
			};

			// Add to database

			// Return
			return TRUE;
		}

		// Add to database without checking... why? who knows......

		// Return
		return FALSE;
	}


	/**
	 * @return mixed|string
	 */
	public function prepareChannelIDs()
	{

		if ( (bool) $this->_data['yt_channel_multiple'] )
		{
			return json_decode( $this->_data['yt_channel_multiple_data'] );
		}

		if ( (bool) $this->_data['yt_channel_id'] )
		{
			return (string) $this->_data['yt_channel_id'];
		}

	}

	/**
	 * Verifies a usernames existence on YouTube
	 *
	 * @note This will return false if no channels are found, even if the username does exist
	 * @param $username The YouTube username
	 * @return bool
	 * @throws InvalidAPIKey
	 * @throws NoAPIKey
	 */
	public function verifyUsername( $username )
	{
		$api_key = $this->hasApiKey();

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

		// If false, username not found OR has no channels
		if ( !$this->parseResponse( $response ) )
		{
			return FALSE;
		}

		return TRUE;
	}


	/**
	 * Get username
	 *
	 * @return string
	 */
	public function get_username()
	{
		return (string) $this->_data['yt_username'];
	}

	/**
	 * Set Multiple
	 *
	 * @param $value
	 */
	public function set_username( $value )
	{
		$this->_data['yt_username'] = (string) $value;
	}

	/**
	 * Get Channel ID
	 *
	 * @return string
	 */
	public function get_channel_id()
	{
		return (string) $this->_data['yt_channel_id'];
	}

	/**
	 * Set Multiple
	 *
	 * @param $value
	 */
	public function set_channel_id( $value )
	{
		$this->_data['yt_channel_id'] = (string) $value;
	}

	/**
	 * Is Multiple?
	 *
	 * @return string
	 */
	public function get_has_multiple()
	{
		return ( (bool) $this->_data['yt_channel_multiple'] );
	}

	/**
	 * Set Multiple
	 *
	 * @param $value
	 */
	public function set_has_multiple( $value )
	{
		$this->_data['yt_channel_multiple'] = ( (bool) $value );
	}

	/**
	 * Get Channel Data
	 *
	 * @return string
	 */
	public function get_has_multiple_data()
	{
		return (string) $this->_data['yt_channel_multiple_data'];
	}

	/**
	 * Set Channel Data
	 *
	 * @param $value
	 */
	public function set_multiple_data( $value )
	{
		$this->_data['yt_channel_multiple_data'] = (string) $value;
	}
}

class NoAPIKey extends \Exception
{
}

class InvalidAPIKey extends \Exception
{
}

class NoSuchUser extends \Exception
{
}