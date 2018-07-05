# API

## Menambahkan Nama

Location : `/addNama`
Method : POST

Parameter

| Parameter | Keterangan |
| --------- | ---------- |
| name      | Nama user  |
| mid       | INA login  |
| nim       | NIM user   |

## List yang Harus di Basecamp

Location : `/listBasecamp`
Method : GET

Return (array)

| Parameter | Keterangan               |
| --------- | ------------------------ |
| name      | Nama yang harus datang   |
| nim       | NIM user                 |
| start     | Start waktu harus datang |

## Saat ingin pulang

Location : `/pulang`
Method : POST

Parameter

| Parameter | Keterangan |
| --------- | ---------- |
| uid       | User id    |

## List yang Ada di Basecamp

Location : /`listCurrent`
Method : GET

Return

| Parameter | Keterangan                              |
| --------- | --------------------------------------- |
| count     | Jumlah yang sedang ada di basecamp      |
| users     | Array berisi siapa yang ada di basecamp |

users (array)

| Parameter | Keterangan                    |
| --------- | ----------------------------- |
| name      | Nama yang sedang ada          |
| nim       | NIM dari user yang sedang ada |

## Keperluan Mendadak

Location : `/tambahBlacklist`
Method : POST

Parameter

| Parameter |                    |
| --------- | ------------------ |
| uid       | User id            |
| reason    | Alasan tidak bisa  |
| tanggal   | Tanggal tidak bisa |

