<?php
/* Dibuat otomatis oleh build.py — jangan diedit di sini.
   Ubah harga di build.py lalu jalankan ulang build-nya. */
return json_decode(<<<'JSON'
{
  "packages": [
    {
      "id": "single",
      "name": "The One & Only",
      "kind": "Single session",
      "tiers": [
        {
          "label": "1 jam",
          "price": 350000,
          "edited": 25,
          "hours": 1
        },
        {
          "label": "2 jam",
          "price": 550000,
          "edited": 35,
          "hours": 2
        }
      ],
      "areas": [
        "its",
        "unair",
        "lain"
      ],
      "transport": [
        "its",
        "unair"
      ],
      "group": false
    },
    {
      "id": "couple",
      "name": "Together We Made It",
      "kind": "Couple session",
      "tiers": [
        {
          "label": "1 jam",
          "price": 500000,
          "edited": 25,
          "hours": 1
        },
        {
          "label": "2,5 jam",
          "price": 799000,
          "edited": 40,
          "hours": 2.5
        }
      ],
      "areas": [
        "its",
        "unair",
        "lain"
      ],
      "transport": [
        "its",
        "unair"
      ],
      "group": false
    },
    {
      "id": "group",
      "name": "Class of 2025",
      "kind": "Group session 3–5 gradumates",
      "tiers": [
        {
          "label": "1 jam",
          "price": 600000,
          "edited": 25,
          "hours": 1
        },
        {
          "label": "2 jam",
          "price": 900000,
          "edited": 40,
          "hours": 2
        }
      ],
      "areas": [
        "its",
        "unair",
        "lain"
      ],
      "transport": [
        "its",
        "unair"
      ],
      "group": true
    },
    {
      "id": "arak",
      "name": "Arak-Arakan",
      "kind": "Arak-arakan session",
      "tiers": [
        {
          "label": "Single, 1 sesi penuh",
          "price": 799000,
          "edited": 30,
          "hours": 3,
          "short": "single"
        },
        {
          "label": "Couple, 1 sesi penuh",
          "price": 1000000,
          "edited": 30,
          "hours": 3,
          "short": "couple"
        }
      ],
      "areas": [
        "its"
      ],
      "transport": [],
      "group": false
    }
  ],
  "addons": [
    {
      "id": "extra30",
      "name": "Extra 30 menit",
      "price": 125000,
      "unit": "30 menit",
      "max": 4,
      "hours": 0.5,
      "desc": "Perpanjang sesi setengah jam. Slot yang kamu pilih harus cukup menampungnya."
    },
    {
      "id": "cover",
      "name": "Sewa cover ijazah",
      "price": 50000,
      "unit": "sesi",
      "max": 1,
      "hours": 0,
      "desc": "Cover ijazah sebagai properti foto, dipakai sepanjang sesi."
    },
    {
      "id": "sameday",
      "name": "Same-day edit 5 foto",
      "price": 100000,
      "unit": "paket",
      "max": 1,
      "hours": 0,
      "desc": "Lima foto pilihanmu diedit dan dikirim 4–5 jam setelah sesi selesai, siap langsung diunggah hari itu juga."
    }
  ],
  "areas": {
    "its": "ITS",
    "unair": "UNAIR",
    "lain": "Kampus / lokasi lain"
  }
}
JSON, true);
