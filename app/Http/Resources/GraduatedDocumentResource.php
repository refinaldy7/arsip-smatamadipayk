<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class GraduatedDocumentResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {
        return [
            'id_siswa' => $this->id,
            'nama_lengkap' => $this->full_name,
            'nisn' => $this->nisn,
            'nis' => $this->nis,
            'tanggal_lahir' => $this->birth_date,
            'tempat_lahir' => $this->birth_place,
            'jenis_kelamin' => $this->gender,
            'slug' => $this->slug,
            'jurusan' => $this->major,
            'id_tahun_akademik' => $this->academic_year_id,
            'foto_siswa' => asset('/images/student_images/' . $this->image),
            'ijazah' => secure_asset('/images/graduated_document/' . $this->graduated_document->ijazah_file),
            'skhun' => secure_asset('/images/graduated_document/' . $this->graduated_document->skhun_file),
            'tahun_lulus' => $this->academic_year->year_start . '/' . $this->academic_year->year_end
        ];
        return parent::toArray($request);
    }
}
