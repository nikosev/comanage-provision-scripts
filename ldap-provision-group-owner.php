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
$mailmanGroups = array(
    "group1",
    "group2",
);
$admins = array();

$baseDN = "ou=,dc=";
$userDN = "ou=,dc=";
$coId = 123;

// Performing SQL query and modify ldap attribute
$sqlquery = "SELECT id, name FROM cm_co_groups WHERE co_id=".$coId." AND co_group_id IS NULL AND NOT deleted ORDER BY id ASC;";
$sqlresult = pg_query($dbconn, $sqlquery) or die('Query failed: ' . pg_last_error());
$sqlgroups = pg_fetch_all($sqlresult);

foreach($sqlgroups as $sqlgroup) {
    //if(strpos($sqlgroup['name'], 'CO:COU:') !== false) {
    //    $sqlgroup['name'] = str_replace('CO:COU:', '', $sqlgroup['name']);
    //}
    if(strpos($sqlgroup['name'], 'admin:') !== false) {
        $sqlgroup['name'] = str_replace('admin:', '', $sqlgroup['name']);
        echo "sqlgroup name=" . $sqlgroup['name'] . "\n";
    } else { // Ignore non admin groups
        continue;
    }
    if(in_array(($sqlgroup['name']), $mailmanGroups)) {
        echo "sqlgroup name=" . $sqlgroup['name'] . " with id '" . $sqlgroup['id']. "' in mailman groups\n";
        if (empty($sqlgroup['id'])) {
            continue;
        } 
        $sqlquery = "SELECT i.identifier FROM cm_co_groups as g "
                  . "INNER JOIN cm_co_group_members as gm ON g.id=gm.co_group_id "
                  . "INNER JOIN cm_identifiers AS i ON gm.co_person_id=i.co_person_id "
                  . "WHERE g.id=".$sqlgroup['id'] . " "
                  . "AND gm.co_group_member_id IS NULL AND NOT gm.deleted "
                  . "AND i.type='uid' AND i.identifier_id IS NULL AND NOT i.deleted "             
                  . "ORDER BY i.identifier";
        $sqlresult = pg_query($dbconn, $sqlquery) or die('Query failed: ' . pg_last_error());
        $adminMembers = pg_fetch_all($sqlresult);
        if (empty($adminMembers)) {
           continue; 
        }
        foreach($adminMembers as $adminMember){
            array_push($admins, "uid=" . $adminMember['identifier'] . "," . $userDN);
        }
        $groupOwners['owner'] = $admins;
        ldap_mod_replace($ldapconn, "cn=" . $sqlgroup['name'] . "," . $baseDN, $groupOwners);
        unset($admins);
        $admins = array();
    }
}

// Free resultset
pg_free_result($sqlresult);

// Closing connection
pg_close($dbconn);
ldap_unbind($ldapconn);

?>
