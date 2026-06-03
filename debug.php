<?php
$ctx1 = stream_context_create(["http"=>["method"=>"POST","header"=>"Content-Type: application/json","content"=>json_encode(["email"=>"finzwork10@gmail.com","password"=>"New@password123"])]]);
$res1 = json_decode(file_get_contents("http://127.0.0.1:8000/api/v1/auth/login", false, $ctx1));
$token = $res1->access_token;

$ctx2 = stream_context_create(["http"=>["method"=>"POST","header"=>"Content-Type: application/json\r\nAuthorization: Bearer $token","content"=>json_encode(["otp"=>"123456"])]]);
file_get_contents("http://127.0.0.1:8000/api/v1/auth/mfa/verify", false, $ctx2);

$ctx3 = stream_context_create(["http"=>["ignore_errors"=>true,"header"=>"Authorization: Bearer $token"]]);
echo file_get_contents("http://127.0.0.1:8000/api/v1/admin/lenders", false, $ctx3);

