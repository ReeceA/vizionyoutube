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
	 * @var bool This _should_ only ever be true, if the construct gets called
	 *           with a Video ID otherwise should not require checking this.
	 *           The same goes if you directly load a video with load()
	 */
	protected $isLoaded = FALSE;

	/**
	 * @var null|string Active channel, same goes with the $isLoaded.
	 *                  This allows video control on a channel-scale
	 */
	public $activeChannel;

	/**
	 * @var null|string Active video, same goes with $isLoaded.
	 *                  This allows control over a single video
	 */
	public $activeVideo;

	/**
	 * @brief    Database table
	 */
	public static $databaseTable = 'vizionyoutube_watchlist';


	/**
	 * By constructing with a Video ID, you gain control of a single video
	 * By constructing with a Channel ID, you gain control of videos on a channel-wide scale
	 * This latter is irrelevant if you statically load a Video ID
	 *
	 * @param string|null $videoID
	 * @param string|null $channelID
	 */
	public function __construct( $videoID = NULL, $channelID = NULL )
	{
		if ( $videoID !== NULL AND $channelID !== NULL )
		{
			throw new  \InvalidArgumentException(
				'Video class construct cannot have both parameters, one has to be NULL'
			);
		}

		// Start 'er up
		parent::__construct();

		if ( $videoID !== NULL )
		{
			parent::load( $videoID );
			$this->activeVideo = $videoID;
			$this->isLoaded = TRUE;
		}
		elseif ( $channelID !== NULL )
		{
			$this->activeChannel = $channelID;
		}


	}

	/**
	 * Return videos for active channel
	 *
	 * @param int $limit Default 25
	 * @return array|null
	 */
	public function all( $limit = 25 )
	{
		// Do we have an active channel set?
		if ( !$this->activeChannel )
		{
			throw new \InvalidArgumentException(
				'A channel ID must be supplied with all(), if you only want a single video, use the constructor or directly load it with the Video ID'
			);
		}

		try
		{
			// Fetch all videos that belong to active channel return as array
			$data = iterator_to_array(
				\IPS\Db::i()->select(
					'*', 'vizionyoutube_videos', array( 'yt_channel_id = ?', $this->activeChannel ),
					'published_date DESC', $limit
				)
			);

			// Create video objects for each video
			$pretty = array();
			foreach ( $data as $ugly )
			{
				$pretty[] = static::constructFromData( $ugly );
			}

			// Return our array that is perhaps full of pretty class-orientated results, or empty.... :3
			return $pretty;

		}
		catch ( \UnderflowException $e )
		{
			// No videos associated with active channel, return NULL
			return NULL;
		}
	}


	/**
	 * Blacklists the video after deleting. To prevent recollection occurring
	 *
	 * @return void
	 */
	public function delete()
	{
		$toBlacklist = array();

		// If loaded, delete active
		if ( isset( $this->_data['id'] ) )
		{
			$toBlacklist[] = $this->_data['yt_video_id'];
			parent::delete();
		}

		// If not load, but have active channel, delete all channel videos
		elseif ( $this->activeChannel )
		{
			try
			{
				$videos = iterator_to_array(
					\IPS\Db::i()->select(
						'*', 'vizionyoutube_videos', array( 'yt_channel_id = ?', $this->activeChannel )
					)
				);

				foreach ( $videos as $video )
				{
					$toBlacklist[] = $video['yt_video_id'];
				}

			}
			catch ( \UnderflowException $e )
			{
				// void out >>>
			}

		}

		// Add blacklists as required
		if ( !empty( $toBlacklist ) )
		{
			foreach ( $toBlacklist as $videoID )
			{
				\IPS\Db::i()->insert(
					'vizionyoutube_video_blacklist', array(
						'yt_video_id' => $videoID,
						'date_added'  => time(),
						'added_by'    => \IPS\Member::loggedIn()->member_id
					)
				);
			}
		}

		// void out >>>>>

	}

}