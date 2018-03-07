while [ $# -gt 0 ]; do
  case "$1" in
    --url=*)
      apiBaseURL="${1#*=}"
      ;;
    --from=*)
      validFrom="${1#*=}"
      ;;
    --through=*)
      validThrough="${1#*=}"
      ;;
    --coId=*)
      coId="${1#*=}"
      ;;
    --stat=*)
      status="${1#*=}"
      ;;
    --typeId=*)
      typeId="${1#*=}"
      ;;
    --id=*)
      identifier="${1#*=}"
      ;;
    --login=*)
      login="${1#*=}"
      ;;
    --given=*)
      given="${1#*=}"
      ;;
    --family=*)
      family="${1#*=}"
      ;;
    --typeAcc=*)
      typeAccount="${1#*=}"
      ;;
    --mail=*)
      mailPerson="${1#*=}"
      ;;
    --verified=*)
      verified="${1#*=}"
      ;;
    --affiliation=*)
      affiliation="${1#*=}"
      ;;
    *)
      printf "***************************\n"
      printf "* Error: Invalid argument.*\n"
      printf "***************************\n"
      exit 1
  esac
  shift
done

echo "apiBaseURL=$apiBaseURL"
echo "validFrom=$validFrom"
echo "validThrough=$validThrough"
echo "coId=$coId"
echo "status=$status"
echo "typeId=$typeId"
echo "identifier=$identifier"
echo "login=$login"
echo "given=$given"
echo "family=$family"
echo "typeAccount=$typeAccount"
echo "mailPerson=$mailPerson"
echo "verified=$verified"
echo "affiliation=$affiliation"
echo " "

CURL='/usr/bin/curl'
CURLARGS="-gvk -H Content-Type:application/json -X POST -u username:password -d"

addOrgIdentity()
{
	coId=$1
	url="${apiBaseURL}/org_identities.json"
  req="{\"RequestType\":\"OrgIdentities\",\"Version\":\"1.0\",\"OrgIdentities\":[{\"Version\":\"1.0\",\"CoId\":\"${coId}\"}]}"
  res="$($CURL $CURLARGS \'$req\' $url)"
  # echo "$res"
  # echo "$req"
  jsonval $(echo "$res") 'Id'
  orgIdentityId=$jsonValue
}

addCoPerson()
{
	coId=$1
	status=$2
	url="${apiBaseURL}/co_people.json"
  req="{\"RequestType\":\"CoPeople\",\"Version\":\"1.0\",\"CoPeople\":[{\"Version\":\"1.0\",\"CoId\":\"${coId}\",\"Status\":\"${status}\"}]}"
  res="$($CURL $CURLARGS \'$req\' $url)"
  # echo "$res"
  # echo "$req"
  jsonval $(echo "$res") 'Id'
  coPersonId=$jsonValue
}

addCoOrgIdentityLink()
{
	coPersonId=$1
	orgIdentityId=$2
	url="${apiBaseURL}/co_org_identity_links.json"
  req="{\"RequestType\":\"CoOrgIdentityLinks\",\"Version\":\"1.0\",\"CoOrgIdentityLinks\":[{\"Version\":\"1.0\",\"CoPersonId\":\"${coPersonId}\",\"OrgIdentityId\":\"${orgIdentityId}\"}]}"
  res="$($CURL $CURLARGS \'$req\' $url)"
  # echo "$res"
  # echo "$req"
}

addIdentifier()
{
	typeId=$1
	identifier=$2
	login=$3
	personType=$4
	personId=$5
	# if [ -n "$login" ]
	# then
	# 	login=true
	# else
	# 	login=false
	# fi
	url="${apiBaseURL}/identifiers.json"
  # TODO change type to eppn 
  req="{\"RequestType\":\"Identifiers\",\"Version\":\"1.0\",\"Identifiers\":[{\"Version\":\"1.0\",\"Type\":\"${typeId}\",\"Identifier\":\"${identifier}\",\"Login\":${login},\"Person\":{\"Type\":\"${personType}\",\"Id\":\"${personId}\"},\"Status\":\"Active\"}]}"
  res="$($CURL $CURLARGS \'$req\' $url)"
  # echo "$res"
  # echo "$req"
}

assignIdentifier()
{
	coPersonId=$1
	url="${apiBaseURL}/identifiers/assign.json"
  req="{\"RequestType\":\"Identifiers\",\"Version\":\"1.0\",\"Identifiers\":[{\"Version\":\"1.0\",\"Person\":{\"Type\":\"CO\",\"Id\":\"${coPersonId}\"}}]}"
  res="$($CURL $CURLARGS \'$req\' $url)"
  # echo "$res"
  # echo "$req"
}

addName()
{
	given=$1
	family=$2
	typeName=$3
	personType=$4
	personId=$5
	url="${apiBaseURL}/names.json"
  req="{\"RequestType\":\"Names\",\"Version\":\"1.0\",\"Names\":[{\"Version\":\"1.0\",\"Given\":\"${given}\",\"Family\":\"${family}\",\"Type\":\"${typeName}\",\"PrimaryName\":true,\"Person\":{\"Type\":\"${personType}\",\"Id\":\"${personId}\"}}]}"
  res="$($CURL $CURLARGS \'$req\' $url)"
  # echo "$res"
  # echo "$req"
}

addEmailAddress()
{
	mailUser=$1
	typeEmail=$2
	verified=$3
	personType=$4
	personId=$5
	url="${apiBaseURL}/email_addresses.json"
  req="{\"RequestType\":\"EmailAddresses\",\"Version\":\"1.0\",\"EmailAddresses\":[{\"Version\":\"1.0\",\"Mail\":\"${mailUser}\",\"Type\":\"${typeEmail}\",\"Verified\":${verified},\"Person\":{\"Type\":\"${personType}\",\"Id\":\"${personId}\"}}]}"
  res="$($CURL $CURLARGS \'$req\' $url)"
  # echo "$CURL $CURLARGS $req $url"
  # echo "$res"
  # echo "$req"
}

addCoPersonRole()
{
	coPersonId=$1
	affiliation=$2
	status=$3
	validFrom=$4
  validThrough=$5
	url="${apiBaseURL}/co_person_roles.json"
  req="{\"RequestType\":\"CoPersonRoles\",\"Version\":\"1.0\",\"CoPersonRoles\":[{\"Version\":\"1.0\",\"Person\":{\"Type\":\"CO\",\"Id\":\"${coPersonId}\"},\"Affiliation\":\"${affiliation}\",\"Status\":\"${status}\",\"ValidFrom\":\"${validFrom}\",\"ValidThrough\":\"${validThrough}\"}]}"
  res="$($CURL $CURLARGS \'$req\' $url)"
  # echo "$CURL $CURLARGS $req $url"
  # echo "$req"
  # echo "$res"
  # echo "$validFrom"
  # echo "$validThrough"
}

jsonval ()
{
  jsonValue=`echo ${1} | sed 's/\\\\\//\//g' | sed 's/[{}]//g' | awk -v k="text" '{n=split($0,a,","); for (i=1; i<=n; i++) print a[i]}' | sed 's/\"\:\"/\|/g' | sed 's/[\,]/ /g' | sed 's/\"//g' | grep -w ${2}| cut -d":" -f2| sed -e 's/^ *//g' -e 's/ *$//g'`
  jsonValue=${jsonValue##*|}
}

addOrgIdentity $coId
addCoPerson $coId $status
addCoOrgIdentityLink $coPersonId $orgIdentityId
if [ -n "$identifier" ]
then
addIdentifier $typeId $identifier $login "Org" $orgIdentityId
fi
assignIdentifier $coPersonId
if [ -n "$given" ]
then
addName $given $family $typeAccount "Org" $orgIdentityId
  addName $given $family $typeAccount "CO" $coPersonId
fi
if [ -n "$mailPerson" ]
then
addEmailAddress $mailPerson $typeAccount $verified "Org" $orgIdentityId
  addEmailAddress $mailPerson $typeAccount $verified "CO" $coPersonId
fi
addCoPersonRole $coPersonId $affiliation $status "$validFrom" "$validThrough"