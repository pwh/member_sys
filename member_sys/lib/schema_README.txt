
Export the schema using phpmyadmin as follows:


Click the home icon, select export
select Export Method->Custom, Format->SQL;   GO

select Format-specific options -> Add DROP DATABASE statement
                               -> Dump table ->  structure
       Object creation options -> Add DROP TABLE ... statement
                               -> CREATE TABLE options -> AUTO_INCREMENT
                               (De-select "Enclose tble and field names..."
       GO

Save the file as schema_<date>.sql

-------

Edit the schema file
    - set the database name to '_mem_sys' everywhere
    - remove all AUTO_INCREMENT = nn at the end of CREATE TABLE statements
    - add this command to enable the event scheduler (if we could) :

/* event scheduler will trim the sessions table periodically (requires priv) */
/* **** SET @@global.event_scheduler=ON; *** */

    - change DROP EVENT  to  DROP EVENT IF EXISTS





