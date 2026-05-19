<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCameraRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'admin';
    }

    public function rules(): array
    {
        $cameraId = $this->route('camera')?->id ?? $this->route('camera');

        return [
            // Tab 1
            'name'        => 'sometimes|string|max:100',
            'zone_id'     => 'sometimes|integer|exists:zones,id,deleted_at,NULL',
            'dvr_channel' => [
                'sometimes', 'string', 'max:10',
                Rule::unique('cameras', 'dvr_channel')->ignore($cameraId)->whereNull('deleted_at'),
            ],
            'description' => 'nullable|string|max:1000',
            'is_active'   => 'sometimes|boolean',

            // Tab 2
            'ip_address'         => ['sometimes', 'string', 'regex:/^(\d{1,3}\.){3}\d{1,3}$|^[0-9a-fA-F:]+$/'],
            'port_onvif'         => [
                'sometimes', 'integer', 'min:1', 'max:65535',
                Rule::unique('cameras')->where(function ($query) {
                    return $query->where('ip_address', $this->ip_address)
                                 ->whereNull('deleted_at');
                })->ignore($cameraId),
            ],
            'port_rtsp'          => 'sometimes|integer|min:1|max:65535',
            'username'           => 'sometimes|string|max:100',
            'password'           => 'sometimes|nullable|string|max:255',
            'rtsp_path'          => 'sometimes|string|max:100',
            'rtsp_transport'     => ['sometimes', Rule::in(['tcp', 'udp'])],
            'connection_timeout' => 'sometimes|integer|min:1|max:60',

            // Tab 3
            'ai_model_path'         => 'sometimes|nullable|string|max:500',
            'detection_size'        => 'sometimes|integer|min:320|max:1280',
            'confidence_threshold'  => 'sometimes|numeric|min:0.1|max:0.95',
            'process_every_n_frame' => 'sometimes|integer|min:1|max:10',
            'class_mapping'         => 'sometimes|nullable|json',
            'auto_screenshot'       => 'sometimes|boolean',
            'screenshot_cooldown'   => 'sometimes|integer|min:5|max:300',

            // Tab 4
            'ptz_enabled'           => 'sometimes|boolean',
            'ptz_speed'             => 'sometimes|numeric|min:0.1|max:1.0',
            'ptz_movement_duration' => 'sometimes|numeric|min:0.1|max:5.0',
            'preset_positions'      => 'sometimes|nullable|json',
        ];
    }

    public function messages(): array
    {
        return [
            'ip_address.regex'  => 'Format IP address tidak valid.',
            'port_onvif.unique' => 'Sudah ada kamera dengan IP dan port ONVIF yang sama.',
            'class_mapping.json' => 'Class mapping harus berformat JSON valid.',
        ];
    }
}
