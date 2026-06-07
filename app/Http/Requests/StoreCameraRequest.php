<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCameraRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'admin';
    }

    public function rules(): array
    {
        return [
            // Tab 1: Informasi Dasar
            'name'        => 'required|string|max:100',
            'zone_id'     => 'required|integer|exists:zones,id,deleted_at,NULL',
            'dvr_channel' => [
                'required', 'string', 'max:10',
                Rule::unique('cameras', 'dvr_channel')->whereNull('deleted_at'),
            ],
            'description' => 'nullable|string|max:1000',
            'is_active'   => 'boolean',

            // Tab 2: Koneksi Jaringan
            'ip_address'         => ['required', 'string', 'regex:/^(\d{1,3}\.){3}\d{1,3}$|^[0-9a-fA-F:]+$/'],
            'port_onvif'         => 'integer|min:1|max:65535',
            'port_rtsp'          => 'integer|min:1|max:65535',
            'username'           => 'required|string|max:100',
            'password'           => 'required|string|max:255',
            'rtsp_path'          => 'string|max:100',
            'rtsp_transport'     => Rule::in(['tcp', 'udp']),
            'connection_timeout' => 'integer|min:1|max:60',

            // Unique IP+port_onvif combination
            'port_onvif' => [
                'integer', 'min:1', 'max:65535',
                Rule::unique('cameras')->where(function ($query) {
                    return $query->where('ip_address', $this->ip_address)
                                 ->whereNull('deleted_at');
                })->ignore($this->camera?->id),
            ],

            // Tab 3: Konfigurasi AI/Deteksi
            'ai_model_path'         => 'nullable|string|max:500',
            'detection_size'        => 'integer|min:320|max:1280',
            'confidence_threshold'  => 'numeric|min:0.1|max:0.95',
            'process_every_n_frame' => 'integer|min:1|max:10',
            'class_mapping'         => 'nullable|json',
            'auto_screenshot'       => 'boolean',
            'screenshot_cooldown'   => 'integer|min:5|max:300',

            // Tab 4: PTZ
            'ptz_enabled'           => 'boolean',
            'ptz_speed'             => 'numeric|min:0.1|max:1.0',
            'ptz_movement_duration' => 'numeric|min:0.1|max:5.0',
            'preset_positions'      => 'nullable|json',
        ];
    }

    public function messages(): array
    {
        return [
            'ip_address.regex'    => 'Format IP address tidak valid.',
            'ip_address.required' => 'IP address wajib diisi.',
            'username.required'   => 'Username kamera wajib diisi.',
            'password.required'   => 'Password kamera wajib diisi.',
            'port_onvif.unique'   => 'Sudah ada kamera dengan IP dan port ONVIF yang sama.',
            'class_mapping.json'  => 'Class mapping harus berformat JSON valid.',
        ];
    }
}
