<?php
class DB extends SQLite3
{
        function __construct( $file )
        {
            $this->open( $file );
        }
}
