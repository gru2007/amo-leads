<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

use Dotzero\LaravelAmoCrm\Facades\AmoCrm;
use League\OAuth2\Client\Token\AccessToken;
use AmoCRM\Models\CustomFieldsValues\PriceCustomFieldValuesModel;
use AmoCRM\Collections\ContactsCollection;
use AmoCRM\Collections\CustomFieldsValuesCollection;
use AmoCRM\Collections\Leads\LeadsCollection;
use AmoCRM\Collections\LinksCollection;
use AmoCRM\Collections\NullTagsCollection;
use AmoCRM\Exceptions\AmoCRMApiException;
use Exception;
use AmoCRM\Filters\LeadsFilter;
use AmoCRM\Models\CompanyModel;
use AmoCRM\Models\ContactModel;
use AmoCRM\Models\CustomFieldsValues\NumericCustomFieldValuesModel;
use AmoCRM\Models\CustomFieldsValues\ValueCollections\NullCustomFieldValueCollection;
use AmoCRM\Models\CustomFieldsValues\ValueCollections\NumericCustomFieldValueCollection;
use AmoCRM\Models\CustomFieldsValues\ValueModels\NumericCustomFieldValueModel;
use AmoCRM\Models\LeadModel;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use League\OAuth2\Client\Token\AccessTokenInterface;
use Illuminate\Support\Sleep;
use App\Jobs\ProcessWebhook;

class ProcessWebhook implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $data;

    /**
     * Create a new job instance.
     */
    public function __construct($data)
    {
        $this->data = $data;

    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $request = $this->data;
        
        
        foreach($this->data as $fields) {
            try {
                foreach($fields["custom_fields"] as $field) {
                    if ($field["name"] == "Себестоимость" && $field["values"] =! null) {
                        $apiClient = new \AmoCRM\Client\AmoCRMApiClient("7425bfb0-6af8-427d-a5dd-e87c7f38f877", "7rKMsNRdFHx4PAWKum5JcLPXu045tVsx9JOXFruKhWV7pKWk08HoVh4m1KuPISFh", "http://localhost:8181/examples/get_token.php");
                        
                        if (!file_exists(DIRECTORY_SEPARATOR . 'tmp' . DIRECTORY_SEPARATOR . 'token_info.json')) {
                            exit('Access token file not found');
                        }
            
                        $access = json_decode(file_get_contents(DIRECTORY_SEPARATOR . 'tmp' . DIRECTORY_SEPARATOR . 'token_info.json'), true);
            
                        if (
                            isset($access)
                            && isset($access['accessToken'])
                            && isset($access['refreshToken'])
                            && isset($access['expires'])
                            && isset($access['baseDomain'])
                        ) {
                            $accessToken = new AccessToken([
                                'access_token' => $access['accessToken'],
                                'refresh_token' => $access['refreshToken'],
                                'expires' => $access['expires'],
                                'baseDomain' => $access['baseDomain'],
                            ]);
                        } else {
                            exit('Invalid access token ' . var_export($access, true));
                        }
                        
                        $apiClient->setAccessToken($accessToken)
                            ->setAccountBaseDomain($accessToken->getValues()['baseDomain']);

                        Sleep::for(rand(1,2))->second();
                        try {
                            //Создадим фильтр по id сделки и ответственному пользователю
                            $filter = new LeadsFilter();
                            $filter->setIds([$fields['id']]);

                            try {
                                //Получим сделки по фильтру и с полем with=is_price_modified_by_robot,loss_reason,contacts
                                $leads = $apiClient->leads()->get($filter);
                            } catch (AmoCRMApiException $e) {
                                Log::error($e);
                                
                            }
                            Sleep::for(rand(1,2))->second();

                            foreach ($leads as $lead) {
                                //Получим коллекцию значений полей сделки
                                $customFields = $lead->getCustomFieldsValues();
                            
                                //Получим значение поля по его ID
                                if (!empty($customFields)) {
                                    $textField = $customFields->getBy('fieldId', 187559);
                                    $textField2 = $customFields->getBy('fieldId', 187557);
                                    if ($textField) {
                                        $textFieldValueCollection = $textField->getValues();
                                    }
                                    if ($textField2) {
                                        $textFieldValueCollection2 = $textField2->getValues();
                                    }
                                }
                            
                                if (empty($textFieldValueCollection)) {
                                    //Если полей нет
                                    $customFields = new CustomFieldsValuesCollection();
                                    $textField = (new NumericCustomFieldValuesModel())->setFieldId(187559);
                                    $textFieldValueCollection = (new NumericCustomFieldValueModel());
                                    $customFields->add($textField);
                                }

                                if($lead->getId() == $fields['id']) {
                                    $textField->setValues(
                                        (new NumericCustomFieldValueCollection())
                                            ->add(
                                                (new NumericCustomFieldValueModel())
                                                    ->setValue($lead->getPrice() - intval($lead->toArray()['custom_fields_values'][0]['values'][0]['value']))
                                            )
                                    );
                                }
                            
                                $lead->setCustomFieldsValues($customFields);
                            }
                            //Сохраним сделку
                            try {
                                $apiClient->leads()->update($leads);
                            } catch (AmoCRMApiException $e) {
                                Log::error($e);
                                
                            }
                            Sleep::for(rand(1,2))->second();


                        } catch (AmoCRMApiException $e) {
                            Log::error($e);
                            
                        }
                    }
                }
            } catch (Exception $e) {
                Log::error($e);
                
            }
        }
    }
}
