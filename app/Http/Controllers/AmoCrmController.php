<?php

namespace App\Http\Controllers;

use AmoCRM\Client\AmoCRMApiClient;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use AmoCRM\Helpers\EntityTypesInterface;
use AmoCRM\Collections\CustomFieldsValuesCollection;
use AmoCRM\Models\CustomFieldsValues\ValueModels\SelectCustomFieldValueModel;
use AmoCRM\Models\CustomFieldsValues\ValueCollections\SelectCustomFieldValueCollection;
use AmoCRM\Models\CustomFieldsValues\ValueModels\MultitextCustomFieldValueModel;
use AmoCRM\Models\CustomFieldsValues\ValueCollections\MultitextCustomFieldValueCollection;
use AmoCRM\Models\CustomFieldsValues\ValueModels\NumericCustomFieldValueModel;
use AmoCRM\Models\CustomFieldsValues\ValueCollections\NumericCustomFieldValueCollection;
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
        $contact = $contactService->getOne('11259657');
        // $this->setContactNumber($contact, 79207499226);
        // $this->setContactEmail($contact, 'dvbvladis@mail.ru');
        // $this->setContactName($contact, 'Владислав', 'Данцаранов');
        // $this->setContactGender($contact, 'Мужской');
        // $contact = $this->setContactAge($contact, 21);
        // $this->apiClient->contacts()->updateOne($contact);
        return view("amo.main");
    }
    public function store(Request $request)
    {
        $request->validate([
            'first_name' => 'required|string',
            'last_name' => 'required|string',
            'age' => 'required|numeric',
            'gender' => 'required|string',
            'phone' => 'required|numeric|digits:11',
            'email' => 'required|email'
        ]);
        $first_name = $request->input('first_name');
        $last_name = $request->input('last_name');
        $age = $request->input('age');
        $gender = $request->input('gender');
        $phone = $request->input('phone');
        $email = $request->input('email');

        $apiClient = $this->apiClient;
        $contactService = $apiClient->contacts();
        
        $contact = new ContactModel();
        //установить кастомные поля как у других контактов
        $contact->setCustomFieldsValues($contactService->get()->last()->getCustomFieldsValues());
        
        $this->setContactNumber($contact, $phone);
        $this->setContactEmail($contact, $email);
        $this->setContactName($contact, $first_name, $last_name);
        $this->setContactAge($contact, $age);
        $this->setContactGender($contact, $gender);
        $apiClient->contacts()->addOne($contact);

        return response()->json([
            'message' => 'ok',
        ], 201);
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
    private function setContactNumber(ContactModel $contact, int $value)
    {
        $contactCustomFields = $contact->getCustomFieldsValues();
        //изменить поле телефон
        $contactCustomFields->getBy('fieldCode', 'PHONE')->setValues(
            (new MultitextCustomFieldValueCollection())->add(
                (new MultitextCustomFieldValueModel())->setValue($value)
            )
        );
        $contact->setCustomFieldsValues($contactCustomFields);
        return $contact;
        // $this->apiClient->contacts()->updateOne($contact);
    }
    private function setContactEmail(ContactModel $contact, string $value)
    {
        $contactCustomFields = $contact->getCustomFieldsValues();
        //изменить поле email
        $contactCustomFields->getBy('fieldCode', 'EMAIL')->setValues(
            (new MultitextCustomFieldValueCollection())->add(
                (new MultitextCustomFieldValueModel())->setValue($value)
            )
        );
        $contact->setCustomFieldsValues($contactCustomFields);
        return $contact;
        // $this->apiClient->contacts()->updateOne($contact);
    }
    private function setContactName(ContactModel $contact, string $first_name, string $last_name)
    {
        $contact->setFirstName($first_name)->setLastName($last_name);
        return $contact;
        // $this->apiClient->contacts()->updateOne($contact);
    }
    private function setContactAge(ContactModel $contact, int $value)
    {
        $contactCustomFields = $contact->getCustomFieldsValues();
        //изменить поле email
        $contactCustomFields->getBy('fieldName', 'Возраст')->setValues(
            (new NumericCustomFieldValueCollection())->add(
                (new NumericCustomFieldValueModel())->setValue($value)
            )
        );
        $contact->setCustomFieldsValues($contactCustomFields);
        return $contact;
        // $this->apiClient->contacts()->updateOne($contact);
    }
    private function setContactGender(ContactModel $contact, string $value)
    {
        $contactCustomFields = $contact->getCustomFieldsValues();
        //изменить поле email
        $contactCustomFields->getBy('fieldName', 'Пол')->setValues(
            (new SelectCustomFieldValueCollection())->add(
                (new SelectCustomFieldValueModel())->setValue($value)
            )
        );
        $contact->setCustomFieldsValues($contactCustomFields);
        return $contact;
        // $this->apiClient->contacts()->updateOne($contact);
    }
}
