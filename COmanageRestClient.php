<?php

/**
 * Authproc filter for retrieving attributes from COmanage and adding them to
 * the list of attributes received from the identity provider.
 *
 * This authproc filter uses the COmanage REST API for retrieving the user's
 * attributes.
 * See https://spaces.internet2.edu/display/COmanage/REST+API
 *
 * Example configuration:
 *
 *    authproc = array(
 *       ...
 *       '60' => array(
 *            'class' => 'attrauthcomanage:COmanageRestClient',
 *            'apiBaseURL' => 'https://comanage.example.org/registry',
 *            'username' => 'bob',
 *            'password' => 'secret',
 *            'userIdAttribute => 'eduPersonUniqueId', 
 *            'urnNamespace' => 'urn:mace:example.org',
 *       ),
 *
 * @author Nicolas Liampotis <nliam@grnet.gr>
 */
class sspmod_attrauthcomanage_Auth_Process_COmanageRestClient
{
    private $apiBaseURL;

    private $username;

    private $password;

    private $userIdAttribute = "eduPersonPrincipalName";

    private $verifyPeer = true;

    private $urnNamespace = "urn:mace:example.org";

    public function __construct($config)
    {
        assert('is_array($config)');

        if (!array_key_exists('apiBaseURL', $config)) {
            SimpleSAML_Logger::error(
                "[attrauthcomanage] Configuration error: 'apiBaseURL' not specified");
            throw new SimpleSAML_Error_Exception(
                "attrauthcomanage configuration error: 'apiBaseURL' not specified"); 
        }
        if (!is_string($config['apiBaseURL'])) {
            SimpleSAML_Logger::error(
                "[attrauthcomanage] Configuration error: 'apiBaseURL' not a string literal");
            throw new SimpleSAML_Error_Exception(
                "attrauthcomanage configuration error: 'apiBaseURL' not a string literal");
        }
        $this->apiBaseURL = $config['apiBaseURL']; 

        if (!array_key_exists('username', $config)) {
            SimpleSAML_Logger::error(
                "[attrauthcomanage] Configuration error: 'username' not specified");
            throw new SimpleSAML_Error_Exception(
                "attrauthcomanage configuration error: 'username' not specified"); 
        }
        if (!is_string($config['username'])) {
            SimpleSAML_Logger::error(
                "[attrauthcomanage] Configuration error: 'username' not a string literal");
            throw new SimpleSAML_Error_Exception(
                "attrauthcomanage configuration error: 'username' not a string literal");
        }
        $this->username = $config['username'];

        if (!array_key_exists('password', $config)) {
            SimpleSAML_Logger::error(
                "[attrauthcomanage] Configuration error: 'password' not specified");
            throw new SimpleSAML_Error_Exception(
                "attrauthcomanage configuration error: 'password' not specified"); 
        }
        if (!is_string($config['password'])) {
            SimpleSAML_Logger::error(
                "[attrauthcomanage] Configuration error: 'password' not a string literal");
            throw new SimpleSAML_Error_Exception(
                "attrauthcomanage configuration error: 'password' not a string literal");
        }
        $this->password = $config['password']; 

        if (array_key_exists('userIdAttribute', $config)) {
            if (!is_string($config['userIdAttribute'])) {
                SimpleSAML_Logger::error(
                    "[attrauthcomanage] Configuration error: 'userIdAttribute' not a string literal");
                throw new SimpleSAML_Error_Exception(
                    "attrauthcomanage configuration error: 'userIdAttribute' not a string literal");
            }
            $this->userIdAttribute = $config['userIdAttribute']; 
        }

        if (array_key_exists('verifyPeer', $config)) {
            if (!is_bool($config['verifyPeer'])) {
                SimpleSAML_Logger::error(
                    "[attrauthcomanage] Configuration error: 'verifyPeer' not a boolean");
                throw new SimpleSAML_Error_Exception(
                    "attrauthcomanage configuration error: 'verifyPeer' not a boolean");
            }
            $this->verifyPeer = $config['verifyPeer']; 
        }

        if (array_key_exists('urnNamespace', $config)) {
            if (!is_string($config['urnNamespace'])) {
                SimpleSAML_Logger::error(
                    "[attrauthcomanage] Configuration error: 'urnNamespace' not a string literal");
                throw new SimpleSAML_Error_Exception(
                    "attrauthcomanage configuration error: 'urnNamespace' not a string literal");
            }
            $this->urnNamespace = $config['urnNamespace']; 
        }
    }

    public function process(&$state)
    {
        try {
            assert('is_array($state)');
            if (empty($state['Attributes'][$this->userIdAttribute])) {
                SimpleSAML_Logger::error(
                    "[attrauthcomanage] Configuration error: 'userIdAttribute' not available");
                throw new SimpleSAML_Error_Exception(
                    "attrauthcomanage configuration error: 'userIdAttribute' not available");
            }
            $identifier = $state['Attributes'][$this->userIdAttribute][0];
            $orgIdentities = $this->_getOrgIdentities($identifier);
            SimpleSAML_Logger::debug(
                "[attrauthcomanage] process: orgIdentities=" 
                . var_export($orgIdentities, true));
            if (empty($orgIdentities)) {
                return; 
            }
            $coOrgIdentityLinks = array();
            foreach ($orgIdentities as $orgIdentity) {
                if (!$orgIdentity->{'Deleted'}) {
                    $coOrgIdentityLinks = array_merge($coOrgIdentityLinks, 
                        $this->_getCoOrgIdentityLinks($orgIdentity->{'Id'}));  
                } 
            }
            SimpleSAML_Logger::debug(
                "[attrauthcomanage] process: coOrgIdentityLinks=" 
                . var_export($coOrgIdentityLinks, true));
            if (empty($coOrgIdentityLinks)) {
                return;
            }
            $coGroups = array();
            foreach ($coOrgIdentityLinks as $coOrgIdentityLink) {
                if (!$coOrgIdentityLink->{'Deleted'}) {
                    $coGroups = array_merge($coGroups,
                        $this->_getCoGroups($coOrgIdentityLink->{'CoPersonId'}));  
                } 
            }
            SimpleSAML_Logger::debug(
                "[attrauthcomanage] process: coGroups=" 
                . var_export($coGroups, true));
            if (empty($coGroups)) {
                return;
            }
            $coGroupMemberships = array();
            foreach ($coGroups as $coGroup) {
                if ($coGroup->{'Status'} === 'Active' && !$coGroup->{'Deleted'}) {
                    $co = $this->_getCo($coGroup->{'CoId'});
                    // CO name should always be available.
                    // However, if for some reason this is not the case, we 
                    // currently resort to using the CO numeric ID.
                    // TODO Consider throwing exception?
                    if (empty($co)) {
                        $coName = $coGroup->{'CoId'};
                    } else {
                        $coName = $co->{'Name'};
                    }
                    $coGroupMemberships[] = array(
                        'groupName' => $coGroup->{'Name'},  
                        'coName' => $coName,  
                    );
                } 
            }
            SimpleSAML_Logger::debug(
                "[attrauthcomanage] process: coGroupMemberships=" 
                . var_export($coGroupMemberships, true));
            if (empty($coGroupMemberships)) {
                return;
            }
            if (!array_key_exists('eduPersonEntitlement', $state['Attributes'])) {
                $state['Attributes']['eduPersonEntitlement'] = array();
            }
            foreach ($coGroupMemberships as $coGroupMembership) {
                $state['Attributes']['eduPersonEntitlement'][] = 
                    $this->urnNamespace . ":"
                    . $coGroupMembership['groupName'] . ":" . "member"
                    . "@" . $coGroupMembership['coName'];
            }
        } catch (\Exception $e) {
            $this->_showException($e);
        }
    }

    public function addOrgIdentity($coId)
    {
        //SimpleSAML_Logger::error("[attrauthcomanage] addOrgIdentity: coId=" // TODO debug
        //    . var_export($coId, true));
        $url = $this->apiBaseURL . "/org_identities.json";
        $req = '{'
           . '"RequestType":"OrgIdentities",'
           . '"Version":"1.0",'
           . '"OrgIdentities":'
           . '['
           . '  {'
           . '     "Version":"1.0",'
           . '     "CoId":"' . $coId . '"'
           . '   }'
           . ']'
           . '}';
        $res = $this->http('POST', $url, $req); 
        return $res;
    }

    public function getOrgIdentities($coId, $identifier)
    {
        // TODO SimpleSAML_Logger::debug("[attrauthcomanage] getOrgIdentities: coId="
        //    . var_export($coId, true) . ", identifier="
        //    . var_export($identifier, true));

        // Construct COmanage REST API URL
        $url = $this->apiBaseURL . "/org_identities.json?"
            . "coid=" . urlencode($coId) . "&"
            . "search.identifier=" . urlencode($identifier);
        $res = $this->http('GET', $url);
        assert('strncmp($res->{"ResponseType"}, "OrgIdentities", 13)===0'); 
        if (empty($res->{'OrgIdentities'})) {
            return array();
        }
        return $res->{'OrgIdentities'};
    }


    public function addCoPerson($coId, $status)
    {
        //SimpleSAML_Logger::error("[attrauthcomanage] addCoPerson: coId=" // TODO debug
        //    . var_export($coId, true) . "status=" . var_export($status, true));
        $url = $this->apiBaseURL . "/co_people.json";
        $req = '{'
           . '"RequestType":"CoPeople",'
           . '"Version":"1.0",'
           . '"CoPeople":'
           . '['
           . '  {'
           . '     "Version":"1.0",'
           . '     "CoId":"' . $coId . '",'
           . '     "Status":"' . $status . '"'
           . '   }'
           . ']'
           . '}';
        $res = $this->http('POST', $url, $req); 
        return $res;
    }

    // Response:
    // {
    //     "ResponseType":"CoPeople",
    //     "Version":"1.0",
    //     "CoPeople":
    //     [
    //         {
    //             "Version":"1.0",
    //             "Id":"<Id>",
    //             "CoId":"<CoId>",
    //             "Status":("Active"|"Approved"|"Confirmed"|"Declined"|"Deleted"|"Denied"|"Duplicate"|"Expired"|"GracePeriod"|"Invited"|"Pending"|"PendingApproval"|"PendingConfirmation"|"Suspended"),
    //             "Created":"<CreateTime>",
    //             "Modified":"<ModTime>"
    //         },
    //         {...}
    //     ]
    //   }
    public function getCoPerson($coPersonId)
    {
        // SimpleSAML_Logger::debug("[attrauthcomanage] getCoPerson: coPersonId=" TODO debug
        //    . var_export($coPersonId, true));

        // Construct COmanage REST API URL
        $url = $this->apiBaseURL . "/co_people/" . urlencode($coPersonId) . ".json";
        $data = $this->http('GET', $url);
        assert('strncmp($data->{"ResponseType"}, "CoPeople", 8)===0'); 
        if (empty($data->{'CoPeople'})) {
            return null;
        }
        return $data->{'CoPeople'}[0];
    }

    public function addCoOrgIdentityLink($coPersonId, $orgIdentityId)
    {
        //SimpleSAML_Logger::error("[attrauthcomanage] addCoOrgIdentityLink:" // TODO debug
        //    . " coPersonId=" . var_export($coPersonId, true) 
        //    . ", orgIdentityId=" . var_export($orgIdentityId, true));
        $url = $this->apiBaseURL . "/co_org_identity_links.json";
        $req = '{'
           . '"RequestType":"CoOrgIdentityLinks",'
           . '"Version":"1.0",'
           . '"CoOrgIdentityLinks":'
           . '['
           . '  {'
           . '     "Version":"1.0",'
           . '     "CoPersonId":"' . $coPersonId . '",'
           . '     "OrgIdentityId":"' . $orgIdentityId . '"'
           . '   }'
           . ']'
           . '}';
        $res = $this->http('POST', $url, $req); 
        return $res;
    }

    // Response:
    // {
    //     "ResponseType":"CoOrgIdentityLinks",
    //     "Version":"1.0",
    //     "CoOrgIdentityLinks":
    //     [
    //         {
    //             "Version":"1.0",
    //             "Id":"<Id>",
    //             "CoPersonId":"<CoPersonId>",
    //             "OrgIdentityId":"<OrgIdentityId>",
    //             "Created":"<CreateTime>",
    //             "Modified":"<ModTime>"
    //         },
    //         {...}
    //     ]
    // }
    public function getCoOrgIdentityLinks($personType, $personId)
    {
        // SimpleSAML_Logger::debug("[attrauthcomanage] getCoOrgIdentityLinks: personType=" TODO debug
        //    . var_export($personType, true) . ", personId="
        //    . var_export($personId, true));
        if (strncmp($personType, "CO", 2) === 0) {
            $personIdType = "copersonid";
        } elseif (strncmp($personType, "Org", 3) === 0) {
            $personIdType = "orgidentityid";
        } else {
            throw new InvalidArgumentException("$personType is not a valid personType");
        }

        // Construct COmanage REST API URL
        $url = $this->apiBaseURL . "/co_org_identity_links.json?$personIdType="
            . urlencode($personId);
        $res = $this->http('GET', $url);
        //assert('strncmp($res->{"ResponseType"}, "CoOrgIdentityLinks", 18)===0'); --> Can't work for HTTP Status 204/404
        if (empty($res->{'CoOrgIdentityLinks'})) {
            return array();
        }
        return $res->{'CoOrgIdentityLinks'};
    }

    public function addIdentifier($type, $identifier, $login, $personType, $personId)
    {
        //SimpleSAML_Logger::error("[attrauthcomanage] addIdentifier: type=" // TODO debug
        //    . var_export($type, true) . ", identifier=" 
        //    . var_export($identifier, true) . ", login="
        //    . var_export($login, true) . ", personType="
        //    . var_export($personType, true) . ", personId="
        //    . var_export($personId, true));
        $url = $this->apiBaseURL . "/identifiers.json";
        $req = '{'
           . '"RequestType":"Identifiers",'
           . '"Version":"1.0",'
           . '"Identifiers":'
           . '['
           . '  {'
           . '     "Version":"1.0",'
           . '     "Type":"' . $type . '",'
           . '     "Identifier":"' . $identifier . '",'
           . '     "Login":' . (($login) ? 'true' : 'false') . ','
           . '     "Person":'
           . '     {'
           . '       "Type":"' . $personType . '",'
           . '       "Id":"' . $personId . '"'
           . '     },'
           . '     "Status":"Active"'
           . '   }'
           . ']'
           . '}';
        $res = $this->http('POST', $url, $req); 
        return $res;
    }

    public function assignIdentifier($personId)
    {
        //SimpleSAML_Logger::error("[attrauthcomanage] assignIdentifier: personId=" // TODO debug
        //    . var_export($personId, true));
        $url = $this->apiBaseURL . "/identifiers/assign.json";
        $req = '{'
           . '"RequestType":"Identifiers",'
           . '"Version":"1.0",'
           . '"Identifiers":'
           . '['
           . '  {'
           . '     "Version":"1.0",'
           . '     "Person":'
           . '     {'
           . '       "Type":"CO",'
           . '       "Id":"' . $personId . '"'
           . '     }'
           . '   }'
           . ']'
           . '}';
        $res = $this->http('POST', $url, $req); 
        return $res;
    }

    // Response:
    // {
    //     "ResponseType":"Identifiers",
    //     "Version":"1.0",
    //     "Identifiers":
    //     [
    //         {
    //             "Version":"1.0",
    //             "Id":"<ID>",
    //             "Type":"<Type>",
    //             "Identifier":"<Identifier>",
    //             "Login":true|false,
    //             "Person":{"Type":("CO"|"Org"),"ID":"<ID>"},
    //             "CoProvisioningTargetId":"<CoProvisioningTargetId>",
    //             "Status":"Active"|"Deleted",
    //             "Created":"<CreateTime>",
    //             "Modified":"<ModTime>"
    //         },
    //         {...}
    //     ]
    //   }
    public function getIdentifiers($personType, $personId)
    {
        //SimpleSAML_Logger::error("[attrauthcomanage] getIdentifiers:" // TODO debug
        //    . var_export($personType, true) . ", personId="
        //    . var_export($personId, true));
        if (strncmp($personType, "CO", 2) === 0) {
            $personIdType = "copersonid";
        } elseif (strncmp($personType, "Org", 3) === 0) {
            $personIdType = "orgidentityid";
        } else {
            throw new InvalidArgumentException("$personType is not a valid personType");
        }

        // Construct COmanage REST API URL
        $url = $this->apiBaseURL . "/identifiers.json?$personIdType="
            . urlencode($personId);
        $res = $this->http('GET', $url);
        assert('strncmp($res->{"ResponseType"}, "Identifiers", 11)===0'); 
        if (empty($res->{'Identifiers'})) {
            return array();
        }
        return $res->{'Identifiers'};
    }

    public function addName($given, $family, $type, $personType, $personId)
    {
        //SimpleSAML_Logger::error("[attrauthcomanage] addName: given=" // TODO debug
        //    . var_export($given, true) . ", family=" 
        //    . var_export($family, true) . ", type="
        //    . var_export($type, true) . ", personType="
        //    . var_export($personType, true) . ", personId="
        //    . var_export($personId, true));
        $url = $this->apiBaseURL . "/names.json";
        $req = '{'
           . '"RequestType":"Names",'
           . '"Version":"1.0",'
           . '"Names":'
           . '['
           . '  {'
           . '     "Version":"1.0",'
           . '     "Given":"' . $given . '",'
           . '     "Family":"' . $family . '",'
           . '     "Type":"' . $type . '",'
           . '     "PrimaryName":true,'
           . '     "Person":'
           . '     {'
           . '       "Type":"' . $personType . '",'
           . '       "Id":"' . $personId . '"'
           . '     }'
           . '   }'
           . ']'
           . '}';
        $res = $this->http('POST', $url, $req); 
        return $res;
    }

    public function addEmailAddress($mail, $type, $verified, $personType, $personId)
    {
        //SimpleSAML_Logger::error("[attrauthcomanage] addEmailAddress: mail=" // TODO debug
        //    . var_export($mail, true) . ", type="
        //    . var_export($type, true) . ", verified="
        //    . var_export($verified, true) . ", personType="
        //    . var_export($personType, true) . ", personId="
        //    . var_export($personId, true));
        $url = $this->apiBaseURL . "/email_addresses.json";
        $req = '{'
           . '"RequestType":"EmailAddresses",'
           . '"Version":"1.0",'
           . '"EmailAddresses":'
           . '['
           . '  {'
           . '     "Version":"1.0",'
           . '     "Mail":"' . $mail . '",'
           . '     "Type":"' . $type . '",'
           . '     "Verified":' . $verified . ','
           . '     "Person":'
           . '     {'
           . '       "Type":"' . $personType . '",'
           . '       "Id":"' . $personId . '"'
           . '     }'
           . '   }'
           . ']'
           . '}';
        $res = $this->http('POST', $url, $req); 
        return $res;
    }

    public function addCoPersonRole($coPersonId, $affiliation, $status, $validFrom, $validThrough)
    {
        //SimpleSAML_Logger::error("[attrauthcomanage] addCoPersonRole:" // TODO debug
        //    . " coPersonId=" . var_export($coPersonId, true)
        //    . ", affiliation=" . var_export($affiliation, true)
        //    . ", status=" . var_export($status, true)
        //    . ", validFrom=" . var_export($status, true)
        //    . ", validThrough=" . var_export($status, true));
        $url = $this->apiBaseURL . "/co_person_roles.json";
        $req = '{'
           . '"RequestType":"CoPersonRoles",'
           . '"Version":"1.0",'
           . '"CoPersonRoles":'
           . '['
           . '  {'
           . '     "Version":"1.0",'
           . '     "Person":'
           . '     {'
           . '       "Type":"CO",'
           . '       "Id":"' . $coPersonId . '"'
           . '     },'
           //TODO . '     "CouId":"' . $couId . '",'
           . '     "Affiliation":"' . $affiliation . '",'
           //TODO . '     "Title":"' . $title . '",'
           //. '     "O":"' . $o . '",'
           //. '     "Ou":"' . $ou . '",'
           . '     "Status":"' . $status . '",'
           . '     "ValidFrom":"' . $validFrom . '",'
           . '     "ValidThrough":"' . $validThrough . '"'
           . '   }'
           . ']'
           . '}';
        $res = $this->http('POST', $url, $req); 
        return $res;
    }

    private function _getOrgIdentities($identifier)
    {
        SimpleSAML_Logger::debug("[attrauthcomanage] _getOrgIdentities: identifier="
            . var_export($identifier, true));

        // Construct COmanage REST API URL
        $url = $this->apiBaseURL . "/org_identities.json?"
            // TODO Limit search to specific CO
            //. "coid=" . $this->_coId . "&"
            . "search.identifier=" . urlencode($identifier);
        $data = $this->http('GET', $url);
        assert('strncmp($data->{"ResponseType"}, "OrgIdentities", 13)===0'); 
        if (empty($data->{'OrgIdentities'})) {
            return array();
        }
        return $data->{'OrgIdentities'};
    }

    private function _getCoOrgIdentityLinks($orgIdentityId)
    {
        SimpleSAML_Logger::debug("[attrauthcomanage] _getCoOrgIdentityLinks: orgIdentityId="
            . var_export($orgIdentityId, true));

        // Construct COmanage REST API URL
        $url = $this->apiBaseURL . "/co_org_identity_links.json?orgidentityid="
            . urlencode($orgIdentityId);
        $data = $this->http('GET', $url);
        assert('strncmp($data->{"ResponseType"}, "CoOrgIdentityLinks", 18)===0'); 
        if (empty($data->{'CoOrgIdentityLinks'})) {
            return array();
        }
        return $data->{'CoOrgIdentityLinks'};
    }

    private function _getCoGroups($coPersonId)
    {
        SimpleSAML_Logger::debug("[attrauthcomanage] _getCoGroups: coPersonId="
            . var_export($coPersonId, true));

        // Construct COmanage REST API URL
        $url = $this->apiBaseURL . "/co_groups.json?"
            . "copersonid=" . urlencode($coPersonId);
        $data = $this->http('GET', $url);
        assert('strncmp($data->{"ResponseType"}, "CoGroups", 8)===0'); 
        if (empty($data->{'CoGroups'})) {
            return array();
        }
        return $data->{'CoGroups'};
    }

    private function _getCo($coId)
    {
        SimpleSAML_Logger::debug("[attrauthcomanage] _getCo: coId="
            . var_export($coId, true));

        // Construct COmanage REST API URL
        $url = $this->apiBaseURL . "/cos/"
            . urlencode($coId) . ".json";
        $data = $this->http('GET', $url);
        assert('strncmp($data->{"ResponseType"}, "Cos", 3)===0'); 
        if (empty($data->{'Cos'})) {
            return null;
        }
        return $data->{'Cos'}[0];
    }

    public function getNames($personType, $personId)
    {
        //SimpleSAML_Logger::error("[attrauthcomanage] addName: given=" // TODO debug
        //    . var_export($given, true) . ", family=" 
        //    . var_export($family, true) . ", type="
        //    . var_export($type, true) . ", personType="
        //    . var_export($personType, true) . ", personId="
        //    . var_export($personId, true));
        // Response
        // {
        //   "ResponseType":"Names",
        //   "Version":"1.0",
        //   "Names":
        //   [
        //     {
        //       "Version":"1.0",
        //       "Id":"<ID>",
        //       "Honorific":"<Honorific>",
        //       "Given":"<Given>",
        //       "Middle":"<Middle>",
        //       "Family":"<Family>",
        //       "Suffix":"<Suffix>",
        //       "Type":"<Type>",
        //       "Language":"<Language>",
        //       "PrimaryName":true|false,
        //       "Person":
        //       {
        //         "Type":("CO"|"Org"),
        //         "Id":"<ID>"
        //       }
        //       "Created":"<CreateTime>",
        //       "Modified":"<ModTime>"
        //     },
        //     {...}
        //   ]
        // }
        if ($personType == "CO") {
            $url = $this->apiBaseURL . "/names.json?copersonid=" . $personId;
        } else {
            $url = $this->apiBaseURL . "/names.json?orgidentityid=" . $personId;
        }
        $res = $this->http('GET', $url);
        assert('strncmp($res->{"ResponseType"}, "Names", 5)===0'); 
        if (empty($res->{'Names'})) {
            return array();
        }
        return $res->{'Names'};
    }

    public function getEmailAddresses($personType, $personId)
    {
        //SimpleSAML_Logger::error("[attrauthcomanage] addEmailAddress: mail=" // TODO debug
        //    . var_export($mail, true) . ", type="
        //    . var_export($type, true) . ", verified="
        //    . var_export($verified, true) . ", personType="
        //    . var_export($personType, true) . ", personId="
        //    . var_export($personId, true));
        // Response
        // {
        //   "ResponseType":"EmailAddresses",
        //   "Version":"1.0",
        //   "EmailAddresses":
        //   [
        //     {
        //       "Version":"1.0",
        //       "Id":"<ID>",
        //       "Mail":"<Mail>",
        //       "Type":<"Type">,
        //       "Verified":true|false,
        //       "Person":
        //       {
        //         "Type":("CO"|"Org"),
        //         "Id":"<ID>"
        //       }
        //       "Created":"<CreateTime>",
        //       "Modified":"<ModTime>"
        //     },
        //     {...}
        //   ]
        // }
        if ($personType == "CO") {
            $url = $this->apiBaseURL . "/email_addresses.json?copersonid=" . $personId;
        } else {
            $url = $this->apiBaseURL . "/email_addresses.json?orgidentityid=" . $personId;
        }
        $res = $this->http('GET', $url); 
        assert('strncmp($res->{"ResponseType"}, "EmailAddresses", 14)===0'); 
        if (empty($res->{'EmailAddresses'})) {
            return array();
        }
        return $res->{'EmailAddresses'};
    }

    public function editNames($nameId, $given, $family, $type, $personType, $personId)
    {
        //SimpleSAML_Logger::error("[attrauthcomanage] addName: given=" // TODO debug
        //    . var_export($given, true) . ", family=" 
        //    . var_export($family, true) . ", type="
        //    . var_export($type, true) . ", personType="
        //    . var_export($personType, true) . ", personId="
        //    . var_export($personId, true));
        $url = $this->apiBaseURL . "/names/" . $nameId .".json";
        $req = '{'
           . '"RequestType":"Names",'
           . '"Version":"1.0",'
           . '"Names":'
           . '['
           . '  {'
           . '     "Version":"1.0",'
           . '     "Given":"' . $given . '",'
           . '     "Family":"' . $family . '",'
           . '     "Type":"' . $type . '",'
           . '     "PrimaryName":true,'
           . '     "Person":'
           . '     {'
           . '       "Type":"' . $personType . '",'
           . '       "Id":"' . $personId . '"'
           . '     }'
           . '   }'
           . ']'
           . '}';
        $res = $this->http('PUT', $url, $req); 
        return $res;
    }

    public function editEmailAddresses($emailId, $mail, $type, $verified, $personType, $personId)
    {
        //SimpleSAML_Logger::error("[attrauthcomanage] addEmailAddress: mail=" // TODO debug
        //    . var_export($mail, true) . ", type="
        //    . var_export($type, true) . ", verified="
        //    . var_export($verified, true) . ", personType="
        //    . var_export($personType, true) . ", personId="
        //    . var_export($personId, true));
        $url = $this->apiBaseURL . "/email_addresses/" . $emailId . ".json";
        $req = '{'
           . '"RequestType":"EmailAddresses",'
           . '"Version":"1.0",'
           . '"EmailAddresses":'
           . '['
           . '  {'
           . '     "Version":"1.0",'
           . '     "Mail":"' . $mail . '",'
           . '     "Type":"' . $type . '",'
           . '     "Verified":' . $verified . ','
           . '     "Person":'
           . '     {'
           . '       "Type":"' . $personType . '",'
           . '       "Id":"' . $personId . '"'
           . '     }'
           . '   }'
           . ']'
           . '}';
        $res = $this->http('PUT', $url, $req); 
        return $res;
    }

    private function http($method, $url, $data = null)
    {
        //SimpleSAML_Logger::error("[attrauthcomanage] http: method=" // TODO debug
        echo "[attrauthcomanage] http: method=" // TODO debug
            . var_export($method, true) . ", url=" . var_export($url, true)
            . ", data=" . var_export($data, true), "\n";
        $ch = curl_init($url);
        curl_setopt_array(
            $ch,
            array(
                CURLOPT_CUSTOMREQUEST => $method,
                CURLOPT_CONNECTTIMEOUT => 5,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_USERPWD => $this->username . ":" . $this->password,
                CURLOPT_SSL_VERIFYPEER => $this->verifyPeer,
            )
        );
        if (($method == "POST" || $method == "PUT") && !empty($data)) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
            curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                'Content-Type: application/json',
                'Content-Length: ' . strlen($data))
            ); 
        }

        // Send the request
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        // Check for error
        if ($http_code !== 200 && $http_code !== 201 && $http_code !== 204 && $http_code !== 302 && $http_code !== 404) {
            //SimpleSAML_Logger::error("[attrauthcomanage] API call failed: HTTP response code: " // TODO
            echo "[attrauthcomanage] API call failed: HTTP response code: " // TODO
                . $http_code . ", error message: '" . curl_error($ch) . "'", "\n";
            // Close session
            curl_close($ch);
            throw new SimpleSAML_Error_Exception("Failed to communicate with COmanage Registry");
        }
        // Close session
        curl_close($ch);
        $result = json_decode($response);
        //SimpleSAML_Logger::error("[attrauthcomanage] http: result=" // TODO
        //    . var_export($result, true));
        assert('json_last_error()===JSON_ERROR_NONE'); 
        return $result;
    }

    private function _showException($e)
    {
        $globalConfig = SimpleSAML_Configuration::getInstance();
        $t = new SimpleSAML_XHTML_Template($globalConfig, 'attrauthcomanage:exception.tpl.php');
        $t->data['e'] = $e->getMessage();
        $t->show();
        exit();
    }
}
