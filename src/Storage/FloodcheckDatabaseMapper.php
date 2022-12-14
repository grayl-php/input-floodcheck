<?php

   namespace Grayl\Input\Floodcheck\Storage;

   use Grayl\Database\Main\DatabasePorter;
   use Grayl\Date\DatePorter;
   use Grayl\Input\Floodcheck\Entity\FloodcheckLog;

   /**
    * Class FloodcheckDatabaseMapper
    * The interface for finding floodcheck logs in the MySQL database and turning them into objects
    *
    * @package Grayl\Input\Floodcheck
    */
   class FloodcheckDatabaseMapper
   {

      /**
       * The name of the database table to query
       *
       * @var string
       */
      private string $table;

      /**
       * A fully configured DatabasePorter
       *
       * @var DatabasePorter
       */
      private DatabasePorter $database_porter;

      /**
       * A fully configured DatePorter
       *
       * @var DatePorter
       */
      private DatePorter $date_porter;


      /**
       * The class constructor
       *
       * @param string         $table           The name of the database table to query
       * @param DatabasePorter $database_porter A fully configured DatabasePorter
       * @param DatePorter     $date_porter     A fully configured DatePorter
       */
      public function __construct ( string $table,
                                    DatabasePorter $database_porter,
                                    DatePorter $date_porter )
      {

         // Set the database table to query
         $this->table = $table;

         // Set the DatabasePorter
         $this->database_porter = $database_porter;

         // Set the DatePorter
         $this->date_porter = $date_porter;

      }


      /**
       * Checks the floodcheck database for existing records using the current hash
       *
       * @param FloodcheckLog $floodcheck_log The FloodcheckLog entity to use for the search
       * @param int           $max_attempts   The amount of duplicate attempts allowed for this log
       *
       * @return bool
       * @throws \Exception
       */
      public function isFloodcheckExceeded ( FloodcheckLog $floodcheck_log,
                                             int $max_attempts ): bool
      {

         // Look for a duplicate tag and IP already in the database
         if ( $this->countMatchingFloodcheckLogs( $floodcheck_log ) >= $max_attempts ) {
            // Return a positive hit
            return true;
         }

         // Return false
         return false;
      }


      /**
       * Returns the number of logs found in a database from a built query
       *
       * @param FloodcheckLog $floodcheck_log The DuplicatorLog entity to use for the search
       *
       * @return int
       * @throws \Exception
       */
      public function countMatchingFloodcheckLogs ( FloodcheckLog $floodcheck_log ): int
      {

         // Get the current time
         $now = $this->date_porter->newDateController( null );

         // Get a new SelectDatabaseController
         $request = $this->database_porter->newSelectDatabaseController( 'default' );

         // Build the query object
         $request->getQueryController()
                 ->select( [ '*' ] )
                 ->from( $this->table )
                 ->where( 'tag',
                          '=',
                          $floodcheck_log->getTag() )
                 ->andwhere( 'ip_address',
                             '=',
                             $floodcheck_log->getIPAddress() )
                 ->andwhere( 'expires',
                             '>',
                             $now->getDateAsString() );

         // Run it and get the result
         $result = $request->runQuery();

         // Return the row count
         return $result->countRows();
      }


      /**
       * Inserts a populated FloodcheckLog entry into the database
       *
       * @param FloodcheckLog $floodcheck_log A populated FloodcheckLog object to save to the database
       * @param int           $max_attempts   The amount of duplicate attempts allowed for this log
       *
       * @return int
       * @throws \Exception
       */
      public function saveFloodcheckLog ( FloodcheckLog $floodcheck_log,
                                          int $max_attempts ): int
      {

         // If this is a duplicate
         if ( $this->isFloodcheckExceeded( $floodcheck_log,
                                           $max_attempts ) ) {
            // Throw an exception
            throw new \Exception( 'Maximum submissions have been exceeded for the allowed time.' );
            // Otherwise log the search
         }
         else {
            // Insert the log
            return $this->insertFloodcheckLog( $floodcheck_log );
         }
      }


      /**
       * Inserts a populated FloodcheckLog entry into the database
       *
       * @param FloodcheckLog $floodcheck_log A populated FloodcheckLog object to save to the database
       *
       * @return int
       * @throws \Exception
       */
      private function insertFloodcheckLog ( FloodcheckLog $floodcheck_log ): int
      {

         // Get a new InsertDatabaseController
         $request = $this->database_porter->newInsertDatabaseController( 'default' );

         // Build the query object and store the ID
         $request->getQueryController()
                 ->insert( [ 'expires'    => $floodcheck_log->getExpires()
                                                            ->getDateAsString(),
                             'tag'        => $floodcheck_log->getTag(),
                             'ip_address' => $floodcheck_log->getIPAddress(), ] )
                 ->into( $this->table );

         // Run it and get the result
         $result = $request->runQuery();

         // Return the ID of the inserted data
         return $result->getReferenceID();
      }

   }