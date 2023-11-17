<?php

namespace App\Http\Controllers;

use AmoCRM\Client\AmoCRMApiClient;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use AmoCRM\Helpers\EntityTypesInterface;

use League\OAuth2\Client\Token\AccessToken;
use AmoCRM\Collections\ContactsCollection;
use AmoCRM\Collections\Leads\LeadsCollection;
use AmoCRM\Models\CompanyModel;
use AmoCRM\Models\ContactModel;
use AmoCRM\Models\LeadModel;

class AmoCrmController extends Controller
{
    private $apiClient;
    public function __construct()
    {
        $clientId = config('amocrm.client_id');
        $clientSecret = config('amocrm.client_secret');
        $redirectUri = config('amocrm.redirect_uri');
        $subdomain = config('amocrm.subdomain');
        $code = config('amocrm.code');
        $this->apiClient = new AmoCRMApiClient($clientId, $clientSecret, $redirectUri);
        $this->apiClient->setAccountBaseDomain($subdomain);
        if (!file_exists('token.json')) {
            $accessToken = $this->apiClient->getOAuthClient()->getAccessTokenByCode($code);
            $this->saveToken(
                [
                    'accessToken' => $accessToken->getToken(),
                    'refreshToken' => $accessToken->getRefreshToken(),
                    'expires' => $accessToken->getExpires(),
                    'baseDomain' => $this->apiClient->getAccountBaseDomain(),
                ]
            );
        } else {
            $accessToken = $this->getToken();
        }
        $this->apiClient->setAccessToken($accessToken)
            ->setAccountBaseDomain('test1screxy.amocrm.ru')
            ->onAccessTokenRefresh(
                function (\League\OAuth2\Client\Token\AccessTokenInterface $accessToken, string $baseDomain) {
                    $this->saveToken(
                        [
                            'accessToken' => $accessToken->getToken(),
                            'refreshToken' => $accessToken->getRefreshToken(),
                            'expires' => $accessToken->getExpires(),
                            'baseDomain' => $baseDomain,
                        ]
                    );
                }
            );
    }
    public function index()
    {
        $apiClient = $this->apiClient;
        $contactService = $apiClient->contacts();
        $contactsCollection = $contactService->get();
        $contact = $contactService->getOne('11259657');
        $contactsCustomFields = $apiClient->customFields(EntityTypesInterface::CONTACTS)->get()->toArray();
        // var_dump($contactsCustomFields);
        var_dump($contact->getCustomFieldsValues());
        // var_dump($contactsCollection->last()->getCustomFieldsValues());
        return view("amo.main");
    }
    public function store(Request $request)
    {
        $request->validate([
            'first_name' => 'required|string',
            'last_name' => 'required|string',
            'age' => 'required|numeric',
            'gender' => 'required|string',
            'phone' => 'required|numeric|digits:10',
            'email' => 'required|email'
        ]);
        $first_name = $request->input('first_name');
        $last_name = $request->input('last_name');
        $age = $request->input('age');
        $gender = $request->input('gender');
        $phone = $request->input('phone');
        $email = $request->input('email');

        $apiClient = $this->apiClient;
        $contact = new ContactModel();
        $contact->setName("$first_name $last_name")->setCustomFieldsValues();

        return response()->json([
            'message' => 'ok',
            'first_name' => "$first_name",
            'last_name' => "$last_name",
            'age' => "$age",
            'gender' => "$gender",
            'phone' => "$phone",
            'email' => "$email",
        ], 200);
    }
    private function saveToken(array $accessToken)
    { {
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

                file_put_contents('token.json', json_encode($data));
            } else {
                exit('Invalid access token ' . var_export($accessToken, true));
            }
        }
    }
    private function getToken()
    {
        if (!file_exists('token.json')) {
            exit('Access token file not found');
        }

        $accessToken = json_decode(file_get_contents('token.json'), true);

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
}
