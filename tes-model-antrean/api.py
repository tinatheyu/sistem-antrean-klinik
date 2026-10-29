
from pathlib import Path
from datetime import datetime

import joblib
import pandas as pd
from fastapi import FastAPI, HTTPException
from pydantic import BaseModel, Field

BASE_DIR = Path(__file__).resolve().parent
MODEL_PATH = BASE_DIR / "Random Forest Antrean.joblib"

model = joblib.load(MODEL_PATH)

app = FastAPI(
    title="API Estimasi Waktu Tunggu Klinik",
    version="1.0.0"
)


class PredictionInput(BaseModel):
    waktu_daftar: datetime
    nomor_antrean_angka: int = Field(ge=1)
    antrean_sebelumnya: int = Field(ge=0)
    cara_daftar: str
    prioritas: str
    sesi_pendaftaran: str


@app.get("/")
def home():
    return {
        "status": "aktif",
        "message": "API estimasi waktu tunggu klinik berjalan"
    }


@app.post("/predict")
def predict(data: PredictionInput):
    try:
        waktu = data.waktu_daftar

        input_data = pd.DataFrame([{
            "Bulan": waktu.month,
            "Hari_Dalam_Minggu": waktu.weekday(),
            "Akhir_Pekan": int(waktu.weekday() >= 5),
            "Jam_Pendaftaran": waktu.hour,
            "Nomor_Antrean_Angka": data.nomor_antrean_angka,
            "Antrean_Sebelumnya": data.antrean_sebelumnya,
            "Cara Daftar": data.cara_daftar,
            "Prioritas": data.prioritas,
            "Sesi_Pendaftaran": data.sesi_pendaftaran,
        }])

        prediksi = float(model.predict(input_data)[0])
        prediksi = max(0.0, prediksi)

        return {
            "estimasi_menit": round(prediksi, 2),
            "waktu_daftar": waktu.isoformat(),
            "catatan": "Estimasi, bukan waktu panggilan yang pasti."
        }

    except Exception as e:
        raise HTTPException(
            status_code=500,
            detail=f"Gagal melakukan prediksi: {str(e)}"
        )
