<?php

namespace App\Http\Controllers;

use App\Models\Student;
use App\Models\Achievement;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use App\Models\AchievementRank;
use App\Models\AchievementCategory;
use Illuminate\Support\Facades\Validator;
use App\Http\Resources\AchievementResource;

class AchievementController extends Controller
{

    public function __construct()
    {
        return auth()->shouldUse('api');
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        return AchievementResource::collection(Achievement::with('students:id,full_name,nisn')->get());
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {

        $messages = [];

        try {
            auth()->userOrFail();
        } catch (\Tymon\JWTAuth\Exceptions\UserNotDefinedException $e) {
            return response()->json('messages : Not Authorized', 403);
        }

        // validasi request
        $validator = Validator::make(request()->all(), [
            'nama_acara' => 'required',
            'penyelenggara' => 'required',
            'tanggal_acara' => 'required',
            'id_juara' => 'required',
            'id_kategori' => 'required',
            'dokumentasi' => 'array',
            'dokumentasi.*' => 'image',
            'piagam' => 'mimes:png,jpg,jpeg,pdf'
        ]);

        if ($validator->fails()) {
            return response()->json($validator->messages(), 417);
        }

        $rank = AchievementRank::where('id', request('id_juara'))->first();
        $category = AchievementCategory::where('id', request('id_kategori'))->first();

        if ($rank === null) {
            return response()->json(['messages' => 'kategori juara tidak ada', 'kode' => '4041'], 404);
        }

        if ($category === null) {
            return response()->json(['messages' => 'kategori lomba tidak ada', 'kode' => '4042'], 404);
        }

        $files = [];
        $eventDate = explode('/', request('tanggal_acara'));
        if ($request->hasFile('dokumentasi')) {
            $i = 0;
            foreach ($request->file('dokumentasi') as $docs) {
                $name = 'dokumentasi-' . Str::slug(strtolower(request('nama_acara')), '-') . '-' . $eventDate[0] . '-' . Str::slug(strtolower(request('penyelenggara')), '-') . '-' . $i .  '.'  . $docs->extension();
                $docs->move(public_path('images/achievement_documentation/'), $name);
                $files[] = $name;
                $i++;
            }
        }
        $documentation = implode(",", $files);
        $charterName  = null;
        if (isset($request->piagam)) {
            $charter = request('piagam');
            $charterName = 'piagam-' . Str::slug(strtolower(request('nama_acara')), '-') . '-' . $eventDate[0] . '-' . Str::slug(request('penyelenggara'), '-') . '.' . $charter->extension();
            $charter->move(public_path('images/achievements_charter/'), $charterName);
        }

        $slug = Str::slug(request('nama_acara'), '-') . '-' . Str::slug(request('tanggal_acara'), '-') . '-' . $rank->rank;
        $achievement = Achievement::create(
            [
                'event_name' => request('nama_acara'),
                'organizer' => request('penyelenggara'),
                'event_date' => request('tanggal_acara'),
                'achievement_documentations' => $documentation,
                'achievement_charter' => $charterName,
                'achievement_rank_id' => request('id_juara'),
                'achievement_category_id' => request('id_kategori'),
                'slug' => $slug
            ]
        );

        $achievementId = $achievement->id;
        if (isset($request->id_siswa)) {
            $studentId = explode(',', $request->id_siswa);

            foreach ($studentId as $student) {
                $studentData = Student::find($student);
                if ($studentData === null) {
                    $achievement->delete();
                    return response()->json(['messages' => 'Salah satu siswa yang dipilih tidak ditemukan'], 404);
                }
                $studentData->achievements()->attach($achievementId);
                $arr[] = $studentData->full_name;
            }
        }

        if (isset($request->nisn_siswa)) {
            $studentId = explode(',', $request->nisn_siswa);
            foreach ($studentId as $student) {
                $studentData = Student::where('nisn', $student)->get()->first();
                if ($studentData === null) {
                    $achievement->delete();
                    return response()->json(['messages' => 'Salah satu siswa yang dipilih tidak ditemukan'], 404);
                }
                $studentData->achievements()->attach($achievementId);
                $arr[] = $studentData->full_name;
            }
        }

        $rank = AchievementRank::where('id', $request->id_juara)->get()->first()->rank;
        $category = AchievementCategory::where('id', $request->id_kategori)->get()->first()->category;

        $messages = [[
            'status' => "SUCCESS",
            'pesan' => "Prestasi baru berhasil ditambahkan",
            'data' => [
                'nama_acara' => request('nama_acara'),
                'penyelenggara' => request('penyelenggara'),
                'tanggal_acara' => request('tanggal_acara'),
                'juara' => $rank,
                'kategori' => $category,
                'dokumentasi' => $documentation != '' ? 'true' : 'null',
                'piagam' => $charterName != '' ? 'true' : 'null'
            ]
        ]];

        return response()->json($messages, 200);
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($attr)
    {
        if (is_numeric($attr)) {
            $achievement = Achievement::find($attr)->with('students:id,full_name,nisn')->get();
        } else {
            $achievement = Achievement::where('slug', $attr)->with('students:id,full_name,nisn')->get();
        }

        if ($achievement != null) {
            return AchievementResource::collection($achievement);
        } else {
            return response()->json(['messages' => 'Data tidak ditemukan'], 404);
        }
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        $messages = [];

        try {
            auth()->userOrFail();
        } catch (\Tymon\JWTAuth\Exceptions\UserNotDefinedException $e) {
            return response()->json('messages : Not Authorized', 403);
        }

        // validasi request
        $validator = Validator::make(request()->all(), [
            'nama_acara' => 'required',
            'penyelenggara' => 'required',
            'tanggal_acara' => 'required',
            'id_juara' => 'required',
            'id_kategori' => 'required',
            'dokumentasi' => 'array',
            'dokumentasi.*' => 'image'
        ]);

        if ($validator->fails()) {
            return response()->json($validator->messages(), 417);
        }

        $files = [];
        $eventDate = explode('/', request('tanggal_acara'));
        if ($request->hasFile('dokumentasi')) {
            $i = 0;
            foreach ($request->file('dokumentasi') as $docs) {
                $name = 'dokumentasi-' . Str::slug(strtolower(request('nama_acara')), '-') . '-' . $eventDate[0] . '-' . Str::slug(strtolower(request('penyelenggara')), '-') . '-' . $i .  '.'  . $docs->extension();
                $docs->move(public_path('images/achievement_documentation/'), $name);
                $files[] = $name;
                $i++;
            }
        }
        $documentation = implode(",", $files);
        $charterName  = null;
        if (isset($request->piagam)) {
            $charter = request('piagam');
            $charterName = 'piagam-' . Str::slug(strtolower(request('nama_acara')), '-') . '-' . $eventDate[0] . '-' . Str::slug(request('penyelenggara'), '-') . '.' . $charter->extension();
            $charter->move(public_path('images/achievements_charter/'), $charterName);
        }

        $achievement = Achievement::find($id);
        $achievement->update(
            [
                'event_name' => request('nama_acara'),
                'organizer' => request('penyelenggara'),
                'event_date' => request('tanggal_acara'),
                'achievement_documentations' => $documentation,
                'achievement_charter' => $charterName,
                'achievement_rank_id' => request('id_juara'),
                'achievement_category_id' => request('id_kategori')
            ]
        );

        $achievementId = $achievement->id;

        if (isset($request->id_siswa)) {
            $studentId = explode(',', $request->id_siswa);
            foreach ($studentId as $student) {
                $studentData = Student::where('id', $student)->get()->first();
                $studentData->achievements()->attach($achievementId);
                $arr[] = $studentData->full_name;
            }
        }

        if (isset($request->nisn_siswa)) {
            $studentId = explode(',', $request->nisn_siswa);
            foreach ($studentId as $student) {
                $studentData = Student::where('nisn', $student)->get()->first();
                $studentData->achievements()->attach($achievementId);
                $arr[] = $studentData->full_name;
            }
        }

        $rank = AchievementRank::where('id', $request->id_juara)->get()->first()->rank;
        $category = AchievementCategory::where('id', $request->id_kategori)->get()->first()->category;

        $messages = [[
            'status' => "SUCCESS",
            'pesan' => "Prestasi berhasil diubah",
            'data' => [
                'nama_acara' => request('nama_acara'),
                'penyelenggara' => request('penyelenggara'),
                'tanggal_acara' => request('tanggal_acara'),
                'juara' => $rank,
                'kategori' => $category,
                'dokumentasi' => $documentation != '' ? 'true' : 'null',
                'piagam' => $charterName != '' ? 'true' : 'null'
            ]
        ]];

        return response()->json($messages, 200);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        //check autentikasi
        try {
            auth()->userOrFail();
        } catch (\Tymon\JWTAuth\Exceptions\UserNotDefinedException $e) {
            return response()->json('messages : Not Authorized', 403);
        }

        $achievement = Achievement::find($id);

        if ($achievement != null) {
            $achievement->delete();
            return response()->json(['messages' => 'Data berhasil dihapus'], 200);
        } else {
            return response()->json(['messages' => 'Data tidak ditemukan'], 404);
        }
    }

    public function getAllCategory()
    {
        $ranks = AchievementRank::all();
        $category = AChievementCategory::all();
        $allCategory = [
            'juara' => $ranks,
            'kategori' => $category
        ];

        return $allCategory;
    }

    public function countAchievement()
    {

        $achievement = Achievement::get();

        $achievementCategories = AchievementCategory::withCount('achievements')->get();
        $arr =  array();
        foreach ($achievementCategories as $achievementCategory) {
            array_push($arr, [
                'kategori' => $achievementCategory->category,
                'total' => $achievementCategory->achievements_count
            ]);
        }

        $achievementData = [
            'jumlah_prestasi' => $achievement->count(),
            'detail_jumlah' => $arr
        ];
        return response()->json([
            'status' => 'success',
            'kode' => '200',
            'pesan' => 'Data berhasil didapatkan',
            'data' => $achievementData
        ], 200);
    }
}
