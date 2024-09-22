<?php 
sqlStatement("CREATE TABLE IF NOT EXISTS user_extension (
	id INT(10) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
	userid INT(100) NOT NULL,
	name VARCHAR(255),
	username VARCHAR(255) NOT NULL,
	extension VARCHAR(255) NOT NULL
	)");
	
$Result = sqlQuery("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'user_extension' AND COLUMN_NAME = 'availability'");
if($Result['COUNT(*)'] == 0) {
		sqlStatement("ALTER TABLE user_extension
	ADD COLUMN availability VARCHAR(255) DEFAULT 'Available'");
}
?>