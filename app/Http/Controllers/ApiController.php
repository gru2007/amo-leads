<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;

use Illuminate\Http\Request;

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

class ApiController extends BaseController
{
    use AuthorizesRequests, DispatchesJobs, ValidatesRequests;
    /**
     * Authenticate the user and get the access token.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function deal(Request $request)
    {
        $action = "";
        if ($request->input("leads.update") != null) {
            $action = "update";
        } elseif ($request->input("leads.add") != null) {
            $action = "add";
        } elseif ($request->input("leads.status") != null) {
            $action = "status";
        } elseif ($request->input("leads.delete") != null) {
            $action = "delete";
        }

        dispatch(new ProcessWebhook($request->input("leads.".$action)));

        return response()->json([
            'status' => 'ok',
            'message' => 'ok',
        ], 200);
    }
}
