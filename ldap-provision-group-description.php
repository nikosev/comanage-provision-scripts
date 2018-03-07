<?php
// PostgreSQL
// Connecting, selecting database
$dbconn = pg_connect("host= dbname= user= password=") or die('Could not connect: ' . pg_last_error());

// LDAP
// Connecting and binding
$ldapconn = ldap_connect("ldaps://localhost") or die("Could not connect to LDAP server.");
if ($ldapconn) {
    ldap_set_option($ldapconn, LDAP_OPT_PROTOCOL_VERSION, 3);
    $l = ldap_bind($ldapconn, 'cn=,ou=,dc=', '') or die ("Error trying to bind: ".ldap_error($ldapconn));
}

// Initialize variables
$baseDN = "ou=,dc=";

// Performing SQL query and modify descriptions
$sqlquery = "SELECT name FROM cm_co_groups WHERE co_group_id IS NULL AND NOT deleted;";
$sqlresult = pg_query($dbconn, $sqlquery) or die('Query failed: ' . pg_last_error());
$sqlgroups = pg_fetch_all($sqlresult);

foreach($sqlgroups as $sqlgroup) {
    if(strpos($sqlgroup['name'], 'members:') !== false) {
        $sqlgroup['name'] = str_replace('members:', '', $sqlgroup['name']);
        $sqlquery = "SELECT name, description FROM cm_cous WHERE name='" . $sqlgroup['name'] . "' AND cou_id IS NULL AND NOT deleted;";
        $result = pg_query($dbconn, $sqlquery) or die('Query failed: ' . pg_last_error());
        $sqldescription = pg_fetch_all($result);
        if (empty($sqldescription[0]['description'])) {
            continue;
        }
        $description['description'] = $sqldescription[0]['description'];
        ldap_mod_replace($ldapconn, "cn=" . $sqlgroup['name'] . "," . $baseDN, $description);
    }
}

// Free resultset
pg_free_result($sqlresult);

// Closing connection
pg_close($dbconn);
ldap_unbind($ldapconn);

?>