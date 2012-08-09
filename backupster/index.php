<?php

/**
 * @author Arashi
 * @copyright 2012
 */
ini_set( 'max_execution_time', 360 );
ini_set( 'memory_limit', '128M' );

include_once("backupster.php");
//var_dump($_POST);
if ($_POST) {
    $bu = new Backupster($_POST['project_name'],$_POST['ftp_server'],$_POST['ftp_path'],$_POST['ftp_user'],$_POST['ftp_password'],$_POST['mysql_server'],$_POST['mysql_dbname'],$_POST['mysql_user'],$_POST['mysql_password']);
    //$bu->backup_files();
    //var_dump($bu);
    $bu->run();
    
    $bu->show_errors();
}

?>
<!DOCTYPE HTML>
<html>
<head>
	<meta http-equiv="content-type" content="text/html" />
	<meta name="author" content="Arashi" />

	<title>Untitled 3</title>
    <style type="text/css">
    <!--
     input {
        display: block;
     }	
    -->
    </style>
</head>

<body>

<form action="<?php echo $_SERVER['PHP_SELF'];?>" method="post" enctype="multipart/form-data">
<input type="text" name="project_name" placeholder="Project name" size="20" />
<fieldset>
<legend>FTP Data</legend>
<input type="text" name="ftp_server" placeholder="FTP serv" size="20" />
<input type="text" name="ftp_user" placeholder="FTP user" size="20" />
<input type="text" name="ftp_password" placeholder="FTP pwd" size="20" />
<input type="text" name="ftp_path" placeholder="FTP path" size="20" />
</fieldset>
<fieldset>
<legend>MYSQL Data</legend>
<input type="text" name="mysql_server" placeholder="MYSQL serv" size="20" />
<input type="text" name="mysql_user" placeholder="MYSQL user" size="20" />
<input type="text" name="mysql_password" placeholder="MYSQL pwd" size="20" />
<input type="text" name="mysql_dbname" placeholder="MYSQL path" size="20" />
</fieldset>
<input type="submit" value="Go" />
</form>

</body>
</html>