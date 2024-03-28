<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Foundation\Validation\ValidatesRequests;
use App\Http\Controllers\Controller;

use Illuminate\Http\Request;

use Dotzero\LaravelAmoCrm\Facades\AmoCrm;

class WebController extends Controller
{
    /**
     * Authenticate the user and get the access token.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function auth(Request $request)
    {
        // Кривова-то но это легко исправить, просто пускай пока будет так
        define('TOKEN_FILE', DIRECTORY_SEPARATOR . 'tmp' . DIRECTORY_SEPARATOR . 'token_info.json');
         /**
         * @param array $accessToken
         */
        function saveToken($accessToken)
        {
            if (
                isset($accessToken)
                && isset($accessToken['accessToken'])
                && isset($accessToken['refreshToken'])
                && isset($accessToken['expires'])
                && isset($accessToken['baseDomain'])
            ) {
                $data = [
                    'accessToken' => $accessToken['accessToken'],
                    'expires' => $accessToken['expires'],
                    'refreshToken' => $accessToken['refreshToken'],
                    'baseDomain' => $accessToken['baseDomain'],
                ];

                file_put_contents(TOKEN_FILE, json_encode($data));
            } else {
                exit('Invalid access token ' . var_export($accessToken, true));
            }
        }

        /**
         * @return AccessToken
         */
        function getToken()
        {
            if (!file_exists(TOKEN_FILE)) {
                exit('Access token file not found');
            }

            $accessToken = json_decode(file_get_contents(TOKEN_FILE), true);

            if (
                isset($accessToken)
                && isset($accessToken['accessToken'])
                && isset($accessToken['refreshToken'])
                && isset($accessToken['expires'])
                && isset($accessToken['baseDomain'])
            ) {
                return new AccessToken([
                    'access_token' => $accessToken['accessToken'],
                    'refresh_token' => $accessToken['refreshToken'],
                    'expires' => $accessToken['expires'],
                    'baseDomain' => $accessToken['baseDomain'],
                ]);
            } else {
                exit('Invalid access token ' . var_export($accessToken, true));
            }
        }

        $apiClient = new \AmoCRM\Client\AmoCRMApiClient("7425bfb0-6af8-427d-a5dd-e87c7f38f877", "7rKMsNRdFHx4PAWKum5JcLPXu045tVsx9JOXFruKhWV7pKWk08HoVh4m1KuPISFh", "https://billing.stormgalaxy.com");
                    
        if (null !== ($request->input('referer'))) {
            $apiClient->setAccountBaseDomain($request->input('referer'));
        }
        
        
        if (null == ($request->input('code'))) {
            $state = bin2hex(random_bytes(16));
            $state = $request->input('oauth2state');
            if (null !== ($request->input('button'))) {
                echo $apiClient->getOAuthClient()->getOAuthButton(
                    [
                        'title' => 'Установить интеграцию',
                        'compact' => true,
                        'class_name' => 'className',
                        'color' => 'default',
                        'error_callback' => 'handleOauthError',
                        'state' => $state,
                    ]
                );
                die;
            } else {
                $authorizationUrl = $apiClient->getOAuthClient()->getAuthorizeUrl([
                    'state' => $state,
                    'mode' => 'post_message',
                ]);
                return redirect($authorizationUrl);
            }
        }
        
        /**
         * Ловим обратный код
         */
        try {
            $accessToken = $apiClient->getOAuthClient()->getAccessTokenByCode($request->input('code'));
        
            if (!$accessToken->hasExpired()) {
                saveToken([
                    'accessToken' => $accessToken->getToken(),
                    'refreshToken' => $accessToken->getRefreshToken(),
                    'expires' => $accessToken->getExpires(),
                    'baseDomain' => $apiClient->getAccountBaseDomain(),
                ]);
            }
        } catch (Exception $e) {
            die((string)$e);
        }
        
        $ownerDetails = $apiClient->getOAuthClient()->getResourceOwner($accessToken);
        
        return response('Hello, '.$ownerDetails->getName().'!', 200);
    }   

   
}
