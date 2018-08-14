<?php
    /* Global Settings */
    $globals = array(
        "prod_dump"     => "/srv/prod_dump.sql",
        "lock_file"     => "migrate.lock"
    );
    $configInput = array (
        "state"         => 0,
        "stratus"       => false,
        "magento"       => 0,
        "origin_port"   => 22,
        "new_root"      => "/srv/public_html/",
        "db_host"       => "mysql"
        );

    class SimpleXMLExtended extends SimpleXMLElement {
        public function addCData( $cData_text )
        {
            $node = dom_import_simplexml( $this );
            $no   = $node->ownerDocument;
            $node->appendChild( $no->createCDataSection( $cData_text ));
        }
    }

    /* Main Loop */
    while(1)
    {
        switch( $configInput['state'] )
        {
            case 1:
                /* Drop local/default web root directory and database to prep for new files coming in */
                echo "Prepping Server:\n";
                echo "\tDropping database.\n";
                //DropDatabaseTables();
                echo "\tDeleting current web root.\n";
                if( file_exists($configInput['new_root']) )
                    echo "rm -rf ". $configInput['new_root'] ."\n";
                    //RunCommand( "rm -rf ".$configInput['new_root'] );
                SwitchState(2);
//                exit(0);
                break;
                
            case 2:
                echo "Copying files from remote.\n";
                Rsync();
                SwitchState(3);
                break;
                
            case 3:
                echo "Getting remote database.\n";
                GetDb();
                SwitchState(4);
                break;
                
            case 4:
                echo "Importing database.\n";
                ImportDb();
                SwitchState(5);
                
            case 5:
                echo "Setting configurations.\n";
                SwitchState(6);
                break;
                
            case 6:
                echo "Cleaning up.\n";
                //unlink( $configInput['lock_file'] );
                echo "\tDone!\n";
                exit(0);
                
            default:
                SetupOptions();
                break;
        }
    }
    

    /* Look for lock file. If it exists, open and read data */
    function SetupOptions()
    {
        global $globals, $configInput;
        
        if( file_exists( $globals['lock_file']) )
        {
            echo "File exists.\n";
            //Load the file
            $contents = file_get_contents( $globals['lock_file'] );
    
            //Decode the JSON data into a PHP array.
            $configInput = json_decode( $contents, true );
            OutputConfig($configInput, false);
            
            if( $configInput['state'] > 0 )
            {
                echo "\n\n[R]estart, [C]ontinue, [E]dit, [J]ump To, or [Q]uit? ";
                $input = trim( fgets(STDIN) );
                
                if( strtolower($input) == 'r')
                {
                    unlink( $globals['lock_file'] );
                    SetupOptions();
                    return;
                }
                else if( strtolower($input) == 'q' )
                {
                    // unlink( $globals['lock_file'] );
                    echo "Goodbye!\n";
                    exit(0);
                }
                /* Need to add edit functionality */
                else if( strtolower($input) == 'j' )
                {
                    jump:
                    // Output Jump menu.
                    echo "\n[1] Clear public_html and db.";
                    echo "\n[2] Rsync Copy Files.";
                    echo "\n[3] Get Remote Database.";
                    echo "\n[4] Import Database.";
                    echo "\n[5] Set Configurations.";
                    echo "\n[6] Cleanup.";
                    
                    echo "\n\nChoice: ";
                    $in = trim( fgets(STDIN) );
                    
                    if( $in > 0 && $in < 7 )
                        SwitchState($in);
                    else
                        goto jump;
                }
                    return;
            }
        }
        else
        {
            /* Lock file doesn't exist, create new file */
            echo "------ Originating Server Info ------\n";
            
            getInput( 'Originating Username', 'origin_user' );
            getInput( 'Originating Server', 'origin_server' );
            getInput( 'Originating Port [22]', 'origin_port' );
            getInput( 'Originating Webroot', 'origin_root' );        
        
            echo "\n\n------ Destination Server Info ------\n";
            
            getInput( 'Database Host [mysql]', 'db_host' );
            getInput( 'Database Name', 'db_name' );
            getInput( 'Database User', 'db_user' );
            getInput( 'Database Pass', 'db_pass' );
            getInput( 'New Webroot [/srv/public_html/]', 'new_root' );
    
            echo "\n\n------ General Info ------\n";
            echo "\nBase URL: ";
            getInput( 'Base URL', 'base_url' );
//            getInput( 'Magento 1 or 2?', 'magento' );
            getInput( 'Additional Rsync Flags (if any)', 'rsync_flags' );
    
            if( strpos($configInput['new_root'], 'srv') !== false )
                $configInput['stratus'] = true;
                
            $configInput['rsync_cmd'] = 'rsync -avz -e "ssh -p '
                .$configInput['origin_port']
                .'" '
                .$configInput['origin_user'].'@'.$configInput['origin_server'].':'.$configInput['origin_root'].' '
                .$configInput['new_root'].' '
                .' --copy-links '.$configInput['rsync_flags'];

            echo "\nRsync Command: \n".$configInput['rsync_cmd'];
            
            getInput( "\nAccept? (Y/n): ", 'rsync_cmd' );
    
            SwitchState(1);
            return;
        }
    }
    
    function OutputConfig($in, $nums=false)
    {
        echo "\n------ Originating Server Info ------";
        printf( "\n%sOriginating Username:\t%s",    $nums===true ? "[ 1] ": "", $in['origin_user'] );
        printf( "\n%sOriginating Server:\t%s",      $nums===true ? "[ 2] ": "", $in['origin_user'] );
        printf( "\n%sOriginating Port:\t%s",        $nums===true ? "[ 3] ": "", $in['origin_port'] );
        printf( "\n%sOriginating Webroot:\t%s",     $nums===true ? "[ 4] ": "", $in['origin_root'] );
        echo "\n\n------ Destination Server Info ------";
        printf( "\n%sDatabase Host:\t%s",           $nums===true ? "[ 5] ": "", $in['db_host'] );
        printf( "\n%sDatabase Name:\t%s",           $nums===true ? "[ 6] ": "", $in['db_name'] );
        printf( "\n%sDatabase User:\t%s",           $nums===true ? "[ 7] ": "", $in['db_user'] );
        printf( "\n%sDatabase Pass:\t%s",           $nums===true ? "[ 8] ": "", $in['db_pass'] );
        printf( "\n%sNew Webroot:\t%s",             $nums===true ? "[ 9] ": "", $in['new_root'] );
        echo "\n\n------ General Info ------";
        printf( "\n%sBase URL:\t\t%s",              $nums===true ? "[10] ": "", $in['base_url'] );
        printf( "\n%sAdditional Rsync Flags:\t%s",  $nums===true ? "[11] ": "", $in['rsync_flags'] );
        printf( "\n%sRsync Command:\t%s",           $nums===true ? "[12] ": "", $in['rsync_cmd'] );
    }

    function SwitchState($state)
    {
        global $configInput, $globals;
        
        $configInput['state'] = $state;
        
        //Encode the array into a JSON string.
        $json = json_encode( $configInput );
        
        //Save the file.
        file_put_contents( $globals['lock_file'], $json );
        return;
    }
    
    function getInput($question, $key)
    {
        global $configInput;
        
        echo "$question: ";
        
        $input = trim( fgets(STDIN) );
        
        /* Check for empty entries, allowing blank origin_port, new_root & rsync_flags */
        if( strlen($input) < 1 && ($key != 'origin_port' && $key != 'new_root' && $key != 'rsync_flags' && $key != 'db_host' && $key != 'rsync_cmd') )
            getInput( $question." (or Q to quit)", $key );

        /* Single character entry - quit signal? */
        else if( strlen( $input ) == 1 )
        {
            if( strtolower($input) == 'q' )
            {
                echo "\nGoodbye!\n";
                exit(0);
            }
            else if( strtolower($input) == 'n' && $key == 'rsync_cmd' )
            {
                echo "\nEnter new rsync command line: \n";
                $input = trim( fgets(STDIN) );
            }
            else if( strtolower($input) == 'y' && $key == 'rsync_cmd' )
            {
                $input = $configInput['rsync_cmd'];
                return;
            }
        }

        /* Sanity checking - slashes at start & end of directory paths */
        if( $key == 'origin_root' || ($key == 'new_root' && strlen($input) > 0) )
        {
            if( $input[0] != '/' )
                $input = '/'.$input;
            if( $input[strlen($input)-1] != '/' )
                $input .= '/';
        }
        
        /* Proper web address given for base url? Default to https:// */            
        if( $key == 'base_url' )
        {
            if( strpos($input, 'http://') === false && strpos($input, 'https://') === false )
                $input = "https://$input";
                
            if( $input[strlen($input)-1] != '/' )
                $input .= '/';
        }

        /* Port was entered, but is not numeric */
        if( $key == 'origin_port' && ( !is_numeric( $input ) && strlen($input) > 0 ) )
            getInput( $question.' (or Q to quit)', $key );

        /* Check Magento version */
        if( $key == 'magento' && ($input != 1 && $input != 2) )
            getInput( $question.' (or Q to quit)', $key );

        /* Don't rewrite value if it is blank to keep defaults */
        if( strlen($input) > 0 )
            $configInput[$key] = $input;
        
        return;
    }


    function DropDatabaseTables()
    {
        global $configInput;
        
        //$mysqli = new mysqli( $configInput['db_host'], $configInput['db_user'], $configInput['db_pass'], $configInput['db_name'] );
        echo "Connection details: ".$configInput['db_host']." ".$configInput['db_user']." ".$configInput['db_pass']." ".$configInput['db_name'];
        //$mysqli->select_db( $configInput['db_name'] );
        
        //$mysqli->query( "SET foreign_key_checks = 0" );
        
        //if( $result = $mysqli->query("SHOW TABLES") )
        //{
        //    while( $row = $result->fetch_array(MYSQLI_NUM) )
        //        $mysqli->query( 'DROP TABLE IF EXISTS '.$row[0] );
        //}
        //$mysqli->query( 'SET foreign_key_checks = 1' );
        //$mysqli->close();
    }
    
    function RunCommand($cmd)
    {
        // Runs generic shell command and streams
        
        while( @ ob_end_flush() ); // End any output buffers
        
        $proc = popen( $cmd, 'r' );
        echo "\n";
        
        while( !feof($proc) )
        {
            echo fread($proc, 4096);
            @ flush();
        }
        echo "\n";
    }
    
    function Rsync()
    {
        global $configInput;
        
        print_r( $configInput['rsync_cmd']."\nPassword:");
        print_r($configInput['ssh_pass']);
        
        while( @ ob_end_flush() ); // End any output buffers
        
        $proc = popen( $cmd, 'r' );
        echo "\n";
        
        while( !feof($proc) )
        {
            echo fread( $proc, 4096 );
            @ flush();
        }
        echo "\n";
        
    }

    function GetDb()
    {
        global $configInput;
        
        $dbInfo = GetDbInfo();
        
        $cmd = 'ssh -p '.$configInput['origin_port'].' '.$configInput['origin_user'].'@'.$configInput['origin_server']
            .' "mysqldump --routines -u '.$dbInfo['db_user'].' -p'.$dbInfo['db_pass'].' '.$dbInfo['db'].' " > '.$configInput['new_root'].'prod_dump.sql';
            
        print_r( $cmd."\nPassword: " );
        print_r( $configInput['ssh_pass'] );
        
        RunCommand( $cmd );
    }
    
    function GetDbInfo()
    {
        global $configInput;
        
        if( file_exists($configInput['new_root']."app/etc/local.xml") )
            $configInput['magento'] = 1;
        else if( file_exists($configInput['new_root']."app/etc/env.php") )
            $configInput['magento'] == 2;
        else
        {
            die( "Error: Could not find local.xml or env.php." );
        }
        
        if( $configInput['magento'] == 2 )
        {
            $path = $configInput['new_root']."app/etc/env.php";
            
            try {
                $data = include $path;
            } catch (\Exception $e) {
                throw new \Exception("Could not open env.php ".$e );
            }
            
            $dbInfo = array (
                'db'        => $data['db']['connection']['default']['dbname'],
                'db_user'   => $data['db']['connection']['default']['username'],
                'db_pass'   => $data['db']['connection']['default']['password'],
                'db_host'   => $data['db']['connection']['default']['host'],
                'prefix'    => $data['db']['table_prefix']
            );
        }
        else
        {
            $path = $configInput['new_root']."app/etc/local.xml";
            $xmlFile = file_get_contents($path);
            $xml = new SimpleXMLExtended($xmlFile);
            
            $dbInfo = array (
                'db'        =>  $xml->global->resources->default_setup->connection->dbname,
                'db_user'   =>  $xml->global->resources->default_setup->connection->username,
                'db_pass'   =>  $xml->global->resources->default_setup->connection->password,
                'db_host'   =>  $xml->global->resources->default_setup->connection->host,
                'prefix'    =>  $xml->global->resources->db->table_prefix
            );
        }
        return $dbInfo;
    }
    
    function ImportDb()
    {
        global $configInput;
        
        RunCommand( "sed -i 's/DEFINER=[^*]*\*/\*/g' ".$configInput['new_root']."prod_dump.sql" );
        
        $cmd = 'mysql -h '.$configInput['db_host'].' -u '.$configInput['db_user'].' -p'.$configInput['db_pass'].' '.$configInput['db_name']
            .' < '.$configInput['new_root'].'prod_dump.sql';
            
        RunCommand( $cmd );
    }