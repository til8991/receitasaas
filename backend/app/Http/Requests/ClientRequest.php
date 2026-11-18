<?php

namespace App\Http\Requests;

use App\Models\Client;
use App\Rules\Cnpj;
use App\Services\Comparador\Premissas;
use App\Support\Documento;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ClientRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // policy checked in the controller (needs the "active" flag)
    }

    protected function prepareForValidation(): void
    {
        $merge = [];
        if ($this->has('cnpj')) {
            $merge['cnpj'] = Documento::limpar($this->input('cnpj'));
        }
        if ($this->has('uf')) {
            $merge['uf'] = strtoupper((string) $this->input('uf'));
        }
        foreach (['default_ncm', 'default_nbs'] as $field) {
            if ($this->filled($field)) {
                $merge[$field] = preg_replace('/\D/', '', (string) $this->input($field));
            }
        }
        $this->merge($merge);
    }

    public function rules(): array
    {
        $creating = $this->isMethod('post');
        $client = $this->route('client');
        $required = $creating ? 'required' : 'sometimes';

        return [
            'cnpj' => [$required, new Cnpj, Rule::unique('clients', 'cnpj')
                ->where('office_id', $this->user()->office_id)
                ->ignore($client instanceof Client ? $client->id : null)],
            'legal_name' => [$required, 'string', 'max:255'],
            'trade_name' => ['nullable', 'string', 'max:255'],
            'uf' => [$required, 'string', 'size:2', Rule::in(self::UFS)],
            'municipality_ibge' => [$required, 'integer', 'digits:7'],
            'current_regime' => ['sometimes', Rule::in(Client::REGIMES)],
            'simples_annex' => ['nullable', Rule::in(Client::ANNEXES)],
            'activities' => ['nullable', 'array'],
            'activities.*' => ['string', 'size:2'],
            'revenue_12m' => ['sometimes', 'numeric', 'min:0', 'max:9999999999999'],
            'purchases_12m' => ['sometimes', 'numeric', 'min:0', 'max:9999999999999'],
            'b2b_sales_pct' => ['sometimes', 'numeric', 'between:0,100'],
            'default_ncm' => ['nullable', 'digits:8'],
            'default_nbs' => ['nullable', 'digits:9'],
            'default_cst' => ['sometimes', 'digits:3'],
            'default_c_class_trib' => ['sometimes', 'digits:6'],
            'assumptions' => ['nullable', 'array'],
            'active' => ['sometimes', 'boolean'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }

    public function validated($key = null, $default = null): mixed
    {
        $data = parent::validated($key, $default);
        if ($key === null && array_key_exists('assumptions', $data) && $data['assumptions'] !== null) {
            $data['assumptions'] = Premissas::validateOverrides($data['assumptions']);
        }

        return $data;
    }

    public function attributes(): array
    {
        return [
            'legal_name' => 'razão social',
            'municipality_ibge' => 'código IBGE do município',
            'simples_annex' => 'anexo do Simples',
            'revenue_12m' => 'faturamento 12 meses',
            'b2b_sales_pct' => '% de vendas B2B',
        ];
    }

    public const UFS = ['AC', 'AL', 'AP', 'AM', 'BA', 'CE', 'DF', 'ES', 'GO', 'MA', 'MT', 'MS', 'MG', 'PA', 'PB', 'PR',
        'PE', 'PI', 'RJ', 'RN', 'RS', 'RO', 'RR', 'SC', 'SP', 'SE', 'TO'];
}
