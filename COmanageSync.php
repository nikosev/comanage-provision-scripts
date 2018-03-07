<?php

include("COmanageRestClient.php");

// $opts = json_decode(base64_decode($argv[1]), true);
// echo date('c'), " ", var_export($opts, true), "\n";
$opts = array(
        'coId'=> 123,
        'orgIdentifier'=>"",
        'givenName'=>"",
        'familyName'=>"",
        'email'=>"",
    );
$comanageClient = new sspmod_attrauthcomanage_Auth_Process_COmanageRestClient(array(
    "apiBaseURL" => "",
    "username" => "",
    "password" => "",
));

$orgIdentities = $comanageClient->getOrgIdentities($opts['coId'], $opts['orgIdentifier']);
if (empty($orgIdentities)) {
    // Create orgIdentity
    $orgIdentity = $comanageClient->addOrgIdentity($opts['coId']);
    // Associate orgIdentifier to orgIdentity
    // TEST $comanageClient->addIdentifier("epuid", $opts['orgIdentifier'],
    $comanageClient->addIdentifier("eppn", $opts['orgIdentifier'],
        true, "Org", $orgIdentity->{'Id'});
} else {
     $orgIdentity = $orgIdentities[0];
}

$coOrgIdentityLinks = $comanageClient->getCoOrgIdentityLinks("Org", $orgIdentity->{'Id'});
if (empty($coOrgIdentityLinks)) {
    $coPerson = $comanageClient->addCoPerson($opts['coId'], "Active");
    $comanageClient->assignIdentifier($coPerson->{'Id'});
    $comanageClient->addCoOrgIdentityLink($coPerson->{'Id'}, $orgIdentity->{'Id'});
} else {
    $coPerson = $comanageClient->getCoPerson($coOrgIdentityLinks[0]->{'CoPersonId'});
}

// if (!empty($opts['givenName']) && !empty($opts['familyName'])) {
//     $comanageClient->addName($opts['givenName'], $opts['familyName'],
//         "official", "Org", $orgIdentity->{'Id'});
//     $comanageClient->addName($opts['givenName'], $opts['familyName'],
//         "official", "CO", $coPerson->{'Id'});
// }

if (!empty($opts['givenName']) && !empty($opts['familyName'])) {
    $orgNames = $comanageClient->getNames("Org", $orgIdentity->{'Id'});
    if (empty($orgNames)) {
        $comanageClient->addName($opts['givenName'], $opts['familyName'],
            "official", "Org", $orgIdentity->{'Id'});
    } else {
        if (($orgNames[0]->{'Given'} != $opts['givenName']) || ($orgNames[0]->{'Family'} != $opts['familyName'])){
            $comanageClient->editNames($orgNames[0]->{'Id'}, $opts['givenName'], $opts['familyName'],
                "official", "Org", $orgIdentity->{'Id'});
        }
    }

    $coNames = $comanageClient->getNames("CO", $coPerson->{'Id'});
    if (empty($coNames)) {
        $comanageClient->addName($opts['givenName'], $opts['familyName'],
            "official", "CO", $coPerson->{'Id'});
    } else {
        if (($coNames[0]->{'Given'} != $opts['givenName']) || ($coNames[0]->{'Family'} != $opts['familyName'])){
            $comanageClient->editNames($coNames[0]->{'Id'}, $opts['givenName'], $opts['familyName'],
                "official", "CO", $coPerson->{'Id'});
        }
    }
}

// if (!empty($opts['email'])) {
//     $comanageClient->addEmailAddress($opts['email'],
//         "official", "true", "Org", $orgIdentity->{'Id'});
//     $comanageClient->addEmailAddress($opts['email'],
//         "official", "true", "CO", $coPerson->{'Id'});
// }

if (!empty($opts['email'])) {
    $orgEmailAddresses = $comanageClient->getEmailAddresses("Org", $orgIdentity->{'Id'});
    if (empty($orgEmailAddresses)) {    
        $comanageClient->addEmailAddress($opts['email'],
            "official", "true", "Org", $orgIdentity->{'Id'});
    } else {
        if ($orgEmailAddresses[0]->{'Mail'} != $opts['email']) {
            $comanageClient->editEmailAddresses($orgEmailAddresses[0]->{'Id'}, $opts['email'],
                "official", "true", "Org", $orgIdentity->{'Id'});
        }
    }

    $coEmailAddresses = $comanageClient->getEmailAddresses("CO", $coPerson->{'Id'});
    if (empty($coEmailAddresses)) {
            $comanageClient->addEmailAddress($opts['email'],
                "official", "true", "CO", $coPerson->{'Id'});
    } else {
        if ($coEmailAddresses[0]->{'Mail'} != $opts['email']) {
            $comanageClient->editEmailAddresses($coEmailAddresses[0]->{'Id'}, $opts['email'],
                "official", "true", "CO", $coPerson->{'Id'});
        }
    }
}

if (!empty($opts['validFrom']) && !empty($opts['validThrough'])) {
    $comanageClient->addCoPersonRole($coPerson->{'Id'}, "member",
        "Active", $opts['validFrom'], $opts['validThrough']);
}

?>
