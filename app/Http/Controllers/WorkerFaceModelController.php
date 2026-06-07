<?php

namespace App\Http\Controllers;

use App\Models\WorkerFaceModel;
use App\Services\DetectionWorkerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class WorkerFaceModelController extends Controller
{
    public function __construct(private DetectionWorkerService $workerService) {}

    public function index(): JsonResponse
    {
        $models = WorkerFaceModel::orderBy('employee_name')->get();
        return response()->json(['data' => $models]);
    }

    /**
     * Upload foto + trigger training di detection worker.
     * Foto dihapus otomatis setelah training selesai.
     */
    public function train(Request $request): JsonResponse
    {
        $request->validate([
            'employee_id'   => 'required|string|max:100',
            'employee_name' => 'required|string|max:255',
            'photos'        => 'required|array|min:3|max:50',
            'photos.*'      => 'required|image|mimes:jpg,jpeg,png|max:10240',
        ]);

        $empId   = preg_replace('/[^a-zA-Z0-9_-]/', '_', $request->employee_id);
        $empName = $request->employee_name;

        // Encode foto ke base64 — kirim langsung ke worker tanpa bergantung pada path file
        $images = [];
        foreach ($request->file('photos') as $photo) {
            $images[] = base64_encode(file_get_contents($photo->getRealPath()));
        }

        // Upsert record DB ke pending
        $record = WorkerFaceModel::updateOrCreate(
            ['employee_id' => $empId],
            ['employee_name' => $empName, 'status' => 'pending', 'error_message' => null]
        );

        // Panggil detection worker untuk training
        try {
            $result = $this->workerService->trainFaceModel($empId, $empName, $images);

            if ($result['success'] ?? false) {
                $record->update([
                    'status'           => 'trained',
                    'embeddings_count' => $result['embeddings_count'] ?? 0,
                    'model_path'       => $result['model_path'] ?? null,
                    'trained_at'       => now(),
                    'error_message'    => null,
                ]);
            } else {
                $record->update([
                    'status'        => 'failed',
                    'error_message' => $result['message'] ?? 'Training gagal.',
                ]);
            }
        } catch (\Exception $e) {
            $record->update(['status' => 'failed', 'error_message' => $e->getMessage()]);
            Log::error("WorkerFaceModel train error: {$e->getMessage()}");
        }

        $record->refresh();

        if ($record->status === 'trained') {
            return response()->json([
                'message' => "Model wajah untuk '{$empName}' berhasil dilatih ({$record->embeddings_count} embedding).",
                'data'    => $record,
            ], 201);
        }

        return response()->json([
            'message' => 'Training gagal: ' . $record->error_message,
            'data'    => $record,
        ], 422);
    }

    /**
     * Hapus model wajah dari DB dan file .pkl di detection worker.
     */
    public function destroy(WorkerFaceModel $workerFaceModel): JsonResponse
    {
        try {
            $this->workerService->deleteFaceModel($workerFaceModel->employee_id);
        } catch (\Exception $e) {
            Log::warning("WorkerFaceModel delete worker error: {$e->getMessage()}");
        }

        $workerFaceModel->delete();

        return response()->json(['message' => "Model wajah '{$workerFaceModel->employee_name}' berhasil dihapus."]);
    }
}
