<?php
  
  
$requestUri = explode('/', trim($_SERVER['REQUEST_URI'],'/'));
$requestParams = $_REQUEST;

//get method
$method = $_SERVER['REQUEST_METHOD'];
if ($method == 'POST' && array_key_exists('HTTP_X_HTTP_METHOD', $_SERVER)) {
    if ($_SERVER['HTTP_X_HTTP_METHOD'] == 'DELETE') {
        $method = 'DELETE';
    } else if ($_SERVER['HTTP_X_HTTP_METHOD'] == 'PUT') {
        $method = 'PUT';
    } else {
        exitWithError('Unexpected Header', 400);
    }
} 

//$requestUri[1] = "api"
//$requestUri[2] - resource(entity )
//$requestUri[3] - selector - id or name

//allowed entities for entityScrud
$entities = array(
'fieldgroups'=>'DefDetailTypeGroups',
'fields'=>'DefDetailTypes',
'rectypegroups'=>'DefRecTypeGroups',
'rectypes'=>'DefRecTypes',
'users'=>'SysUsers',
'groups'=>'SysGroups',
'records'=>'Records'
);
//  http://127.0.0.1/h5-ao/api/fieldgroups/?db=osmak_5
//records 
    //record_details - batch actions for records
    //record_search
    //record_edit

//auth
    //usr_info

//echo print_r($requestUri,true);
//echo '<br>'.$method;

if(count($requestUri)>0){
    $last_one = $requestUri[count($requestUri)-1];
    $k = strpos($last_one, '?');
    if($k>0){
        $requestUri[count($requestUri)-1] = substr($last_one,0,$k);
    }
}

if(@$requestUri[1]!== 'api' || @$entities[@$requestUri[2]] == null){
    exitWithError('API Not Found', 400);
}

$method = getAction($method);
if($method == null){
    exitWithError('Method Not Allowed', 405);
}

if($method=='save'){
    $data = json_decode(file_get_contents('php://input'), true);
    
//DEBUG error_log(print_r($data,true));    
    //request body
    $_REQUEST['fields'] = $data;
    if(@$_REQUEST['fields']['db']){
        $_REQUEST['db'] = $_REQUEST['fields']['db'];
        unset($_REQUEST['fields']['db']);
    }
}else{
    
    if(@$_REQUEST['limit']==null || $_REQUEST['limit']>50 || $_REQUEST['limit']<1){
        $_REQUEST['limit']=50;
    }
    
}



// throw new RuntimeException('Unauthorized - authentication failed', 401);

//action
$_REQUEST['entity'] = $entities[$requestUri[2]];
$_REQUEST['a'] = $method;
$_REQUEST['restapi'] = 1; //set http response code

if(@$requestUri[3]!=null){
  $_REQUEST['recID'] = $requestUri[3];  
} 

if($_REQUEST['entity']=='Records'){
    if($method=='search'){
        include '../../hsapi/controller/record_output.php';
    }else{
        exitWithError('Method Not Allowed', 405);
    }
}else{
    include '../../hsapi/controller/entityScrud.php';
}
exit();
//header("HTTP/1.1 " . $status . " " . $this->requestStatus($status));
//echo json_encode($data); 

function exitWithError($message, $code){
    
    header("Access-Control-Allow-Origin: *");
    header('Content-type: application/json;charset=UTF-8'); //'text/javascript');
    
    http_response_code($code);    
    print json_encode(array("status"=>'invalid', "message"=>$message));
    exit();
}

function getAction($method){
    if($method=='GET'){
        return 'search';
    }else if($method=='POST'){ //add new 
        return 'save';
    }else if($method=='PUT'){ //replace
        return 'save';
    }else if($method=='DELETE'){
        return 'delete';
    }       
    return null;
}
?>
