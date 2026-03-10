--
-- PostgreSQL database dump
--

-- Dumped from database version 17.4
-- Dumped by pg_dump version 17.4

SET statement_timeout = 0;
SET lock_timeout = 0;
SET idle_in_transaction_session_timeout = 0;
SET transaction_timeout = 0;
SET client_encoding = 'UTF8';
SET standard_conforming_strings = on;
SELECT pg_catalog.set_config('search_path', '', false);
SET check_function_bodies = false;
SET xmloption = content;
SET client_min_messages = warning;
SET row_security = off;

--
-- Name: catat_log_absensi_santri(); Type: FUNCTION; Schema: public; Owner: postgres
--

CREATE FUNCTION public.catat_log_absensi_santri() RETURNS trigger
    LANGUAGE plpgsql
    AS $$
BEGIN
    -- Hanya mencatat JIKA kolom status_kehadiran benar-benar diubah
    IF OLD.status_kehadiran IS DISTINCT FROM NEW.status_kehadiran THEN
        INSERT INTO log_perubahan_absensi (
            tabel_terkait,
            id_record,
            field_diubah,
            nilai_lama,
            nilai_baru,
            alasan_perubahan,
            diubah_oleh
        ) VALUES (
            'absensi_santri',
            NEW.id_absensi,
            'status_kehadiran',
            OLD.status_kehadiran,
            NEW.status_kehadiran,
            'Diubah via sistem', -- Bisa kamu sesuaikan nanti
            NEW.input_oleh
        );
    END IF;
    RETURN NEW;
END;
$$;


ALTER FUNCTION public.catat_log_absensi_santri() OWNER TO postgres;

--
-- Name: update_updated_at_column(); Type: FUNCTION; Schema: public; Owner: postgres
--

CREATE FUNCTION public.update_updated_at_column() RETURNS trigger
    LANGUAGE plpgsql
    AS $$
BEGIN
    NEW.updated_at = CURRENT_TIMESTAMP;
    RETURN NEW;
END;
$$;


ALTER FUNCTION public.update_updated_at_column() OWNER TO postgres;

SET default_tablespace = '';

SET default_table_access_method = heap;

--
-- Name: absensi_pengajar; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.absensi_pengajar (
    id_abs_pengajar integer NOT NULL,
    id_petugas integer NOT NULL,
    id_sesi integer,
    tanggal date NOT NULL,
    status_kehadiran character varying(20) NOT NULL,
    menit_terlambat integer DEFAULT 0,
    keterangan text,
    input_oleh integer,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT absensi_pengajar_status_kehadiran_check CHECK (((status_kehadiran)::text = ANY (ARRAY[('HADIR'::character varying)::text, ('IZIN'::character varying)::text, ('SAKIT'::character varying)::text, ('ALPHA'::character varying)::text, ('TERLAMBAT'::character varying)::text])))
);


ALTER TABLE public.absensi_pengajar OWNER TO postgres;

--
-- Name: absensi_pengajar_id_abs_pengajar_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.absensi_pengajar_id_abs_pengajar_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.absensi_pengajar_id_abs_pengajar_seq OWNER TO postgres;

--
-- Name: absensi_pengajar_id_abs_pengajar_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.absensi_pengajar_id_abs_pengajar_seq OWNED BY public.absensi_pengajar.id_abs_pengajar;


--
-- Name: absensi_santri; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.absensi_santri (
    id_absensi integer NOT NULL,
    id_sesi integer NOT NULL,
    nomor_induk character varying(20) NOT NULL,
    status_kehadiran character varying(10) NOT NULL,
    keterangan text,
    timestamp_input timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    input_oleh integer,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    updated_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT absensi_santri_status_kehadiran_check CHECK (((status_kehadiran)::text = ANY (ARRAY[('HADIR'::character varying)::text, ('IZIN'::character varying)::text, ('SAKIT'::character varying)::text, ('ALPHA'::character varying)::text])))
);


ALTER TABLE public.absensi_santri OWNER TO postgres;

--
-- Name: absensi_santri_id_absensi_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.absensi_santri_id_absensi_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.absensi_santri_id_absensi_seq OWNER TO postgres;

--
-- Name: absensi_santri_id_absensi_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.absensi_santri_id_absensi_seq OWNED BY public.absensi_santri.id_absensi;


--
-- Name: administrasi_bebas; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.administrasi_bebas (
    id_admin_bebas integer NOT NULL,
    id_santri integer,
    deskripsi text,
    total_tagihan numeric,
    sisa numeric,
    status character varying(30),
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);


ALTER TABLE public.administrasi_bebas OWNER TO postgres;

--
-- Name: administrasi_bebas_id_admin_bebas_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.administrasi_bebas_id_admin_bebas_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.administrasi_bebas_id_admin_bebas_seq OWNER TO postgres;

--
-- Name: administrasi_bebas_id_admin_bebas_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.administrasi_bebas_id_admin_bebas_seq OWNED BY public.administrasi_bebas.id_admin_bebas;


--
-- Name: akun_pendaftar; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.akun_pendaftar (
    id_akun integer NOT NULL,
    nama character varying(200) NOT NULL,
    email character varying(150),
    phone character varying(30),
    password_hash character varying(255),
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    updated_at timestamp without time zone
);


ALTER TABLE public.akun_pendaftar OWNER TO postgres;

--
-- Name: akun_pendaftar_id_akun_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.akun_pendaftar_id_akun_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.akun_pendaftar_id_akun_seq OWNER TO postgres;

--
-- Name: akun_pendaftar_id_akun_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.akun_pendaftar_id_akun_seq OWNED BY public.akun_pendaftar.id_akun;


--
-- Name: data_akun_santri; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.data_akun_santri (
    id_akun_santri integer NOT NULL,
    nomor_induk character varying(20) NOT NULL,
    nama_akun character varying(100) NOT NULL,
    nama_lengkap character varying(200),
    nama_unit character varying(100),
    nama_kelas character varying(100),
    tahun_ajaran character varying(20),
    alamat_email character varying(100),
    nomor_telepon character varying(20),
    password_hash character varying(255),
    status character varying(20) DEFAULT 'AKTIF'::character varying,
    last_login timestamp without time zone,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    updated_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);


ALTER TABLE public.data_akun_santri OWNER TO postgres;

--
-- Name: TABLE data_akun_santri; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON TABLE public.data_akun_santri IS 'Data akun login santri untuk sistem';


--
-- Name: COLUMN data_akun_santri.nomor_induk; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.data_akun_santri.nomor_induk IS 'Foreign key ke data_santri';


--
-- Name: COLUMN data_akun_santri.nama_akun; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.data_akun_santri.nama_akun IS 'Username untuk login (biasanya sama dengan nomor_induk)';


--
-- Name: COLUMN data_akun_santri.password_hash; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.data_akun_santri.password_hash IS 'Password terenkripsi (bcrypt/hash)';


--
-- Name: COLUMN data_akun_santri.last_login; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.data_akun_santri.last_login IS 'Timestamp login terakhir';


--
-- Name: data_akun_santri_id_akun_santri_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.data_akun_santri_id_akun_santri_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.data_akun_santri_id_akun_santri_seq OWNER TO postgres;

--
-- Name: data_akun_santri_id_akun_santri_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.data_akun_santri_id_akun_santri_seq OWNED BY public.data_akun_santri.id_akun_santri;


--
-- Name: data_kategori_tagihan; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.data_kategori_tagihan (
    id_kategori integer NOT NULL,
    pilihan_unit character varying(10),
    kode_kategori character varying(20) NOT NULL,
    nama_tagihan character varying(200) NOT NULL,
    biaya_tagihan numeric(15,2) NOT NULL,
    keterangan text,
    status character varying(20) DEFAULT 'AKTIF'::character varying,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    updated_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);


ALTER TABLE public.data_kategori_tagihan OWNER TO postgres;

--
-- Name: TABLE data_kategori_tagihan; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON TABLE public.data_kategori_tagihan IS 'Master kategori tagihan/pembayaran';


--
-- Name: COLUMN data_kategori_tagihan.pilihan_unit; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.data_kategori_tagihan.pilihan_unit IS 'Kode unit atau SEMUA untuk semua unit';


--
-- Name: COLUMN data_kategori_tagihan.biaya_tagihan; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.data_kategori_tagihan.biaya_tagihan IS 'Nominal biaya dalam Rupiah';


--
-- Name: data_kategori_tagihan_id_kategori_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.data_kategori_tagihan_id_kategori_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.data_kategori_tagihan_id_kategori_seq OWNER TO postgres;

--
-- Name: data_kategori_tagihan_id_kategori_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.data_kategori_tagihan_id_kategori_seq OWNED BY public.data_kategori_tagihan.id_kategori;


--
-- Name: data_kelas; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.data_kelas (
    id_kelas integer NOT NULL,
    kode_unit character varying(10) NOT NULL,
    kode_kelas character varying(10) NOT NULL,
    nama_kelas character varying(100) NOT NULL,
    nama_jurusan character varying(100),
    tahun_ajaran character varying(20) NOT NULL,
    status character varying(20) DEFAULT 'AKTIF'::character varying,
    status_ppdb character varying(20),
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    updated_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);


ALTER TABLE public.data_kelas OWNER TO postgres;

--
-- Name: TABLE data_kelas; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON TABLE public.data_kelas IS 'Data kelas per unit dan tahun ajaran';


--
-- Name: COLUMN data_kelas.kode_kelas; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.data_kelas.kode_kelas IS 'Kode unik kelas';


--
-- Name: COLUMN data_kelas.tahun_ajaran; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.data_kelas.tahun_ajaran IS 'Tahun ajaran kelas ini (contoh: 2025/2026)';


--
-- Name: data_kelas_id_kelas_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.data_kelas_id_kelas_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.data_kelas_id_kelas_seq OWNER TO postgres;

--
-- Name: data_kelas_id_kelas_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.data_kelas_id_kelas_seq OWNED BY public.data_kelas.id_kelas;


--
-- Name: data_kelas_mapel; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.data_kelas_mapel (
    id_kelas_mapel integer NOT NULL,
    kode_kelas character varying(10) NOT NULL,
    kode_mapel character varying(20) NOT NULL,
    id_petugas integer,
    tahun_ajaran character varying(20) NOT NULL,
    semester smallint NOT NULL,
    buku_acuan character varying(200),
    status character varying(20) DEFAULT 'AKTIF'::character varying,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    updated_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT data_kelas_mapel_semester_check CHECK ((semester = ANY (ARRAY[1, 2])))
);


ALTER TABLE public.data_kelas_mapel OWNER TO postgres;

--
-- Name: TABLE data_kelas_mapel; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON TABLE public.data_kelas_mapel IS 'Mapping kelas-mapel-pengajar per semester';


--
-- Name: COLUMN data_kelas_mapel.buku_acuan; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.data_kelas_mapel.buku_acuan IS 'Referensi kitab/buku, tampil di kolom Keterangan raport';


--
-- Name: data_kelas_mapel_id_kelas_mapel_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.data_kelas_mapel_id_kelas_mapel_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.data_kelas_mapel_id_kelas_mapel_seq OWNER TO postgres;

--
-- Name: data_kelas_mapel_id_kelas_mapel_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.data_kelas_mapel_id_kelas_mapel_seq OWNED BY public.data_kelas_mapel.id_kelas_mapel;


--
-- Name: data_konversi_nilai; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.data_konversi_nilai (
    id_konversi integer NOT NULL,
    kode_unit character varying(10),
    nilai_min numeric(5,2) NOT NULL,
    nilai_max numeric(5,2) NOT NULL,
    nilai_huruf character varying(5) NOT NULL,
    predikat character varying(50),
    status character varying(20) DEFAULT 'AKTIF'::character varying,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);


ALTER TABLE public.data_konversi_nilai OWNER TO postgres;

--
-- Name: TABLE data_konversi_nilai; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON TABLE public.data_konversi_nilai IS 'Master konversi nilai angka ke huruf/predikat';


--
-- Name: COLUMN data_konversi_nilai.kode_unit; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.data_konversi_nilai.kode_unit IS 'NULL = berlaku untuk semua unit';


--
-- Name: data_konversi_nilai_id_konversi_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.data_konversi_nilai_id_konversi_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.data_konversi_nilai_id_konversi_seq OWNER TO postgres;

--
-- Name: data_konversi_nilai_id_konversi_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.data_konversi_nilai_id_konversi_seq OWNED BY public.data_konversi_nilai.id_konversi;


--
-- Name: data_mata_pelajaran; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.data_mata_pelajaran (
    id_mapel integer NOT NULL,
    kode_mapel character varying(20) NOT NULL,
    nama_mapel character varying(200) NOT NULL,
    kode_unit character varying(10),
    kelompok_mapel character varying(50),
    urutan integer DEFAULT 0,
    keterangan text,
    status character varying(20) DEFAULT 'AKTIF'::character varying,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    updated_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);


ALTER TABLE public.data_mata_pelajaran OWNER TO postgres;

--
-- Name: TABLE data_mata_pelajaran; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON TABLE public.data_mata_pelajaran IS 'Master mata pelajaran per unit';


--
-- Name: COLUMN data_mata_pelajaran.kelompok_mapel; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.data_mata_pelajaran.kelompok_mapel IS 'Diniyah / Umum / Bahasa';


--
-- Name: data_mata_pelajaran_id_mapel_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.data_mata_pelajaran_id_mapel_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.data_mata_pelajaran_id_mapel_seq OWNER TO postgres;

--
-- Name: data_mata_pelajaran_id_mapel_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.data_mata_pelajaran_id_mapel_seq OWNED BY public.data_mata_pelajaran.id_mapel;


--
-- Name: data_nilai_siswa; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.data_nilai_siswa (
    id_nilai integer NOT NULL,
    nomor_induk character varying(20) NOT NULL,
    kode_mapel character varying(20) NOT NULL,
    kode_kelas character varying(10) NOT NULL,
    tahun_ajaran character varying(20) NOT NULL,
    semester smallint NOT NULL,
    nilai_harian numeric(5,2),
    nilai_uts numeric(5,2),
    nilai_uas numeric(5,2),
    nilai_akhir numeric(5,2) GENERATED ALWAYS AS (round((((COALESCE(nilai_harian, (0)::numeric) * 0.4) + (COALESCE(nilai_uts, (0)::numeric) * 0.3)) + (COALESCE(nilai_uas, (0)::numeric) * 0.3)), 2)) STORED,
    keterangan text,
    id_petugas_input integer,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    updated_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT data_nilai_siswa_semester_check CHECK ((semester = ANY (ARRAY[1, 2])))
);


ALTER TABLE public.data_nilai_siswa OWNER TO postgres;

--
-- Name: TABLE data_nilai_siswa; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON TABLE public.data_nilai_siswa IS 'Nilai harian/UTS/UAS per santri per mapel per semester';


--
-- Name: COLUMN data_nilai_siswa.nilai_akhir; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.data_nilai_siswa.nilai_akhir IS 'Generated: 40% harian + 30% UTS + 30% UAS';


--
-- Name: data_nilai_siswa_id_nilai_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.data_nilai_siswa_id_nilai_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.data_nilai_siswa_id_nilai_seq OWNER TO postgres;

--
-- Name: data_nilai_siswa_id_nilai_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.data_nilai_siswa_id_nilai_seq OWNED BY public.data_nilai_siswa.id_nilai;


--
-- Name: data_petugas; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.data_petugas (
    id_petugas integer NOT NULL,
    nomor_induk character varying(20),
    nama_lengkap character varying(200) NOT NULL,
    peran_akun character varying(50) NOT NULL,
    pilihan_unit character varying(10),
    alamat_email character varying(100) NOT NULL,
    nomor_telepon character varying(20),
    password_hash character varying(255),
    status character varying(20) DEFAULT 'AKTIF'::character varying,
    last_login timestamp without time zone,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    updated_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);


ALTER TABLE public.data_petugas OWNER TO postgres;

--
-- Name: TABLE data_petugas; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON TABLE public.data_petugas IS 'Data petugas/admin sistem';


--
-- Name: COLUMN data_petugas.peran_akun; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.data_petugas.peran_akun IS 'Role/jabatan (Petugas Admin, Petugas Keuangan, dll)';


--
-- Name: COLUMN data_petugas.pilihan_unit; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.data_petugas.pilihan_unit IS 'Unit yang dikelola atau SEMUA';


--
-- Name: COLUMN data_petugas.password_hash; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.data_petugas.password_hash IS 'Password terenkripsi';


--
-- Name: data_petugas_id_petugas_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.data_petugas_id_petugas_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.data_petugas_id_petugas_seq OWNER TO postgres;

--
-- Name: data_petugas_id_petugas_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.data_petugas_id_petugas_seq OWNED BY public.data_petugas.id_petugas;


--
-- Name: data_raport; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.data_raport (
    id_raport integer NOT NULL,
    nomor_induk character varying(20) NOT NULL,
    kode_kelas character varying(10) NOT NULL,
    tahun_ajaran character varying(20) NOT NULL,
    semester smallint NOT NULL,
    jumlah_nilai numeric(8,2) DEFAULT 0,
    rata_rata numeric(5,2) DEFAULT 0,
    peringkat_kelas integer,
    total_siswa_kelas integer,
    hadir integer DEFAULT 0,
    sakit integer DEFAULT 0,
    izin integer DEFAULT 0,
    alpha integer DEFAULT 0,
    status_raport character varying(20) DEFAULT 'DRAFT'::character varying,
    catatan_wali text,
    id_wali_kelas integer,
    tanggal_terbit date,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    updated_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT data_raport_semester_check CHECK ((semester = ANY (ARRAY[1, 2])))
);


ALTER TABLE public.data_raport OWNER TO postgres;

--
-- Name: TABLE data_raport; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON TABLE public.data_raport IS 'Header/rekap raport per santri per semester';


--
-- Name: COLUMN data_raport.status_raport; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.data_raport.status_raport IS 'DRAFT > FINAL > PUBLISHED (santri/wali bisa lihat & unduh)';


--
-- Name: data_raport_id_raport_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.data_raport_id_raport_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.data_raport_id_raport_seq OWNER TO postgres;

--
-- Name: data_raport_id_raport_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.data_raport_id_raport_seq OWNED BY public.data_raport.id_raport;


--
-- Name: data_rekening_bank; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.data_rekening_bank (
    id_rekening integer NOT NULL,
    kode_unit character varying(10),
    kode_rekening character varying(20) NOT NULL,
    nama_rekening character varying(200) NOT NULL,
    nama_pemilik character varying(200) NOT NULL,
    nomor_rekening character varying(50) NOT NULL,
    nama_bank character varying(100) NOT NULL,
    cabang_bank character varying(200),
    logo_bank character varying(255),
    peruntukan text,
    status character varying(20) DEFAULT 'AKTIF'::character varying,
    is_connect boolean DEFAULT false,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    updated_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);


ALTER TABLE public.data_rekening_bank OWNER TO postgres;

--
-- Name: TABLE data_rekening_bank; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON TABLE public.data_rekening_bank IS 'Data rekening bank untuk penerimaan pembayaran';


--
-- Name: COLUMN data_rekening_bank.kode_unit; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.data_rekening_bank.kode_unit IS 'Foreign key ke data_unit';


--
-- Name: COLUMN data_rekening_bank.nomor_rekening; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.data_rekening_bank.nomor_rekening IS 'Nomor rekening bank - unique';


--
-- Name: COLUMN data_rekening_bank.logo_bank; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.data_rekening_bank.logo_bank IS 'Path/URL file logo bank';


--
-- Name: COLUMN data_rekening_bank.peruntukan; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.data_rekening_bank.peruntukan IS 'Deskripsi peruntukan rekening';


--
-- Name: COLUMN data_rekening_bank.is_connect; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.data_rekening_bank.is_connect IS 'Status koneksi payment gateway otomatis';


--
-- Name: data_rekening_bank_id_rekening_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.data_rekening_bank_id_rekening_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.data_rekening_bank_id_rekening_seq OWNER TO postgres;

--
-- Name: data_rekening_bank_id_rekening_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.data_rekening_bank_id_rekening_seq OWNED BY public.data_rekening_bank.id_rekening;


--
-- Name: data_santri; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.data_santri (
    id_santri integer NOT NULL,
    nomor_induk character varying(20) NOT NULL,
    nama_lengkap_santri character varying(200) NOT NULL,
    kode_kelas character varying(10) NOT NULL,
    status character varying(20) DEFAULT 'AKTIF'::character varying,
    tahun_masuk integer,
    tahun_lulus integer,
    jenis_kelamin character(1),
    tempat_lahir character varying(100),
    tanggal_lahir date,
    agama character varying(50),
    berat_badan numeric(5,2),
    tinggi_badan numeric(5,2),
    gol_darah character varying(5),
    provinsi character varying(100),
    kota_kabupaten character varying(100),
    kecamatan character varying(100),
    kelurahan character varying(100),
    alamat_tinggal text,
    nomor_telepon character varying(20),
    alamat_email character varying(100),
    nama_ayah_kandung character varying(200),
    nama_ibu_kandung character varying(200),
    nama_wali character varying(200),
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    updated_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT data_santri_jenis_kelamin_check CHECK ((jenis_kelamin = ANY (ARRAY['L'::bpchar, 'P'::bpchar])))
);


ALTER TABLE public.data_santri OWNER TO postgres;

--
-- Name: TABLE data_santri; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON TABLE public.data_santri IS 'Data lengkap santri/siswa';


--
-- Name: COLUMN data_santri.nomor_induk; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.data_santri.nomor_induk IS 'Nomor induk siswa (NIS) - unique';


--
-- Name: COLUMN data_santri.jenis_kelamin; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.data_santri.jenis_kelamin IS 'L = Laki-laki, P = Perempuan';


--
-- Name: COLUMN data_santri.nama_wali; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.data_santri.nama_wali IS 'Wali santri jika bukan orang tua kandung';


--
-- Name: data_santri_id_santri_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.data_santri_id_santri_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.data_santri_id_santri_seq OWNER TO postgres;

--
-- Name: data_santri_id_santri_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.data_santri_id_santri_seq OWNED BY public.data_santri.id_santri;


--
-- Name: data_tahun_ajaran; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.data_tahun_ajaran (
    id_tahun_ajaran integer NOT NULL,
    kode_tahun character varying(20) NOT NULL,
    nama_tahun character varying(50) NOT NULL,
    keterangan text,
    status character varying(20) DEFAULT 'AKTIF'::character varying,
    is_deleted boolean DEFAULT false,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    updated_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    deleted_at timestamp without time zone
);


ALTER TABLE public.data_tahun_ajaran OWNER TO postgres;

--
-- Name: TABLE data_tahun_ajaran; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON TABLE public.data_tahun_ajaran IS 'Master data tahun ajaran';


--
-- Name: COLUMN data_tahun_ajaran.kode_tahun; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.data_tahun_ajaran.kode_tahun IS 'Kode tahun ajaran (contoh: 2025/2026)';


--
-- Name: COLUMN data_tahun_ajaran.is_deleted; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.data_tahun_ajaran.is_deleted IS 'Soft delete marker';


--
-- Name: COLUMN data_tahun_ajaran.deleted_at; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.data_tahun_ajaran.deleted_at IS 'Tanggal penghapusan (soft delete)';


--
-- Name: data_tahun_ajaran_id_tahun_ajaran_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.data_tahun_ajaran_id_tahun_ajaran_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.data_tahun_ajaran_id_tahun_ajaran_seq OWNER TO postgres;

--
-- Name: data_tahun_ajaran_id_tahun_ajaran_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.data_tahun_ajaran_id_tahun_ajaran_seq OWNED BY public.data_tahun_ajaran.id_tahun_ajaran;


--
-- Name: data_unit; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.data_unit (
    id_unit integer NOT NULL,
    kode_unit character varying(10) NOT NULL,
    nama_unit character varying(100) NOT NULL,
    nomor_urut integer,
    keterangan text,
    status character varying(20) DEFAULT 'AKTIF'::character varying,
    status_ppdb character varying(20),
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    updated_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);


ALTER TABLE public.data_unit OWNER TO postgres;

--
-- Name: TABLE data_unit; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON TABLE public.data_unit IS 'Master data unit pendidikan (PAUD, TK, SD, SMP, SMA, dll)';


--
-- Name: COLUMN data_unit.id_unit; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.data_unit.id_unit IS 'Primary key auto increment';


--
-- Name: COLUMN data_unit.kode_unit; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.data_unit.kode_unit IS 'Kode unik unit (contoh: PAUD, TK, SD)';


--
-- Name: COLUMN data_unit.status_ppdb; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.data_unit.status_ppdb IS 'Status penerimaan peserta didik baru';


--
-- Name: data_unit_id_unit_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.data_unit_id_unit_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.data_unit_id_unit_seq OWNER TO postgres;

--
-- Name: data_unit_id_unit_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.data_unit_id_unit_seq OWNED BY public.data_unit.id_unit;


--
-- Name: jadwal_pembelajaran; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.jadwal_pembelajaran (
    id_jadwal integer NOT NULL,
    id_kelas_mapel integer NOT NULL,
    tahun_ajaran character varying(20) NOT NULL,
    hari character varying(10) NOT NULL,
    jam_mulai time without time zone NOT NULL,
    jam_selesai time without time zone NOT NULL,
    ruangan character varying(50),
    status character varying(20) DEFAULT 'AKTIF'::character varying,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    updated_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);


ALTER TABLE public.jadwal_pembelajaran OWNER TO postgres;

--
-- Name: jadwal_pembelajaran_id_jadwal_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.jadwal_pembelajaran_id_jadwal_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.jadwal_pembelajaran_id_jadwal_seq OWNER TO postgres;

--
-- Name: jadwal_pembelajaran_id_jadwal_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.jadwal_pembelajaran_id_jadwal_seq OWNED BY public.jadwal_pembelajaran.id_jadwal;


--
-- Name: kwitansi_pdf; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.kwitansi_pdf (
    id_kwitansi integer NOT NULL,
    id_pembayaran integer,
    id_petugas integer,
    jenis character varying(50),
    jumlah numeric,
    file_path_pdf text,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);


ALTER TABLE public.kwitansi_pdf OWNER TO postgres;

--
-- Name: kwitansi_pdf_id_kwitansi_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.kwitansi_pdf_id_kwitansi_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.kwitansi_pdf_id_kwitansi_seq OWNER TO postgres;

--
-- Name: kwitansi_pdf_id_kwitansi_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.kwitansi_pdf_id_kwitansi_seq OWNED BY public.kwitansi_pdf.id_kwitansi;


--
-- Name: log_aktivitas; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.log_aktivitas (
    id_log_aktivitas integer NOT NULL,
    id_petugas integer,
    jenis_aksi character varying(50) NOT NULL,
    modul character varying(50) NOT NULL,
    deskripsi text,
    ip_address character varying(45),
    user_agent text,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);


ALTER TABLE public.log_aktivitas OWNER TO postgres;

--
-- Name: log_aktivitas_id_log_aktivitas_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.log_aktivitas_id_log_aktivitas_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.log_aktivitas_id_log_aktivitas_seq OWNER TO postgres;

--
-- Name: log_aktivitas_id_log_aktivitas_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.log_aktivitas_id_log_aktivitas_seq OWNED BY public.log_aktivitas.id_log_aktivitas;


--
-- Name: log_download_raport; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.log_download_raport (
    id_log integer NOT NULL,
    id_raport integer NOT NULL,
    nomor_induk character varying(20),
    id_petugas integer,
    tipe_pengunduh character varying(20) DEFAULT 'SANTRI'::character varying,
    aksi character varying(30) DEFAULT 'DOWNLOAD'::character varying,
    nama_file_pdf character varying(255),
    ip_address character varying(45),
    user_agent text,
    status_aksi character varying(20) DEFAULT 'SUKSES'::character varying,
    keterangan text,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);


ALTER TABLE public.log_download_raport OWNER TO postgres;

--
-- Name: TABLE log_download_raport; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON TABLE public.log_download_raport IS 'Audit log generate/download PDF e-raport';


--
-- Name: COLUMN log_download_raport.tipe_pengunduh; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.log_download_raport.tipe_pengunduh IS 'SANTRI | WALI | PETUGAS';


--
-- Name: COLUMN log_download_raport.aksi; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.log_download_raport.aksi IS 'GENERATE | DOWNLOAD | CETAK';


--
-- Name: log_download_raport_id_log_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.log_download_raport_id_log_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.log_download_raport_id_log_seq OWNER TO postgres;

--
-- Name: log_download_raport_id_log_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.log_download_raport_id_log_seq OWNED BY public.log_download_raport.id_log;


--
-- Name: log_perubahan_absensi; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.log_perubahan_absensi (
    id_log integer NOT NULL,
    tabel_terkait character varying(50) NOT NULL,
    id_record integer NOT NULL,
    field_diubah character varying(50) NOT NULL,
    nilai_lama text,
    nilai_baru text,
    alasan_perubahan text,
    diubah_oleh integer,
    diubah_pada timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    ip_address character varying(45)
);


ALTER TABLE public.log_perubahan_absensi OWNER TO postgres;

--
-- Name: log_perubahan_absensi_id_log_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.log_perubahan_absensi_id_log_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.log_perubahan_absensi_id_log_seq OWNER TO postgres;

--
-- Name: log_perubahan_absensi_id_log_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.log_perubahan_absensi_id_log_seq OWNED BY public.log_perubahan_absensi.id_log;


--
-- Name: pembayaran_spp; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.pembayaran_spp (
    id_pembayaran integer NOT NULL,
    id_santri integer,
    id_setting integer,
    nominal_bayar numeric,
    tanggal_bayar timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    metode_bayar character varying(50),
    id_rekening integer,
    status character varying(30)
);


ALTER TABLE public.pembayaran_spp OWNER TO postgres;

--
-- Name: pembayaran_spp_id_pembayaran_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.pembayaran_spp_id_pembayaran_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.pembayaran_spp_id_pembayaran_seq OWNER TO postgres;

--
-- Name: pembayaran_spp_id_pembayaran_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.pembayaran_spp_id_pembayaran_seq OWNED BY public.pembayaran_spp.id_pembayaran;


--
-- Name: ppdb_berkas; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.ppdb_berkas (
    id_berkas integer NOT NULL,
    id_pendaftaran integer,
    jenis_berkas character varying(80),
    file_path text,
    uploaded_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);


ALTER TABLE public.ppdb_berkas OWNER TO postgres;

--
-- Name: ppdb_berkas_id_berkas_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.ppdb_berkas_id_berkas_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.ppdb_berkas_id_berkas_seq OWNER TO postgres;

--
-- Name: ppdb_berkas_id_berkas_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.ppdb_berkas_id_berkas_seq OWNED BY public.ppdb_berkas.id_berkas;


--
-- Name: ppdb_notifikasi; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.ppdb_notifikasi (
    id_notif integer NOT NULL,
    id_pendaftaran integer,
    type character varying(20),
    konten text,
    sent_at timestamp without time zone,
    status_kirim character varying(20)
);


ALTER TABLE public.ppdb_notifikasi OWNER TO postgres;

--
-- Name: ppdb_notifikasi_id_notif_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.ppdb_notifikasi_id_notif_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.ppdb_notifikasi_id_notif_seq OWNER TO postgres;

--
-- Name: ppdb_notifikasi_id_notif_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.ppdb_notifikasi_id_notif_seq OWNED BY public.ppdb_notifikasi.id_notif;


--
-- Name: ppdb_pendaftar; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.ppdb_pendaftar (
    id_pendaftaran integer NOT NULL,
    id_akun integer,
    no_pendaftaran character varying(50) NOT NULL,
    no_pendaftaran_final character varying(50),
    nama_calon character varying(200) NOT NULL,
    jenjang character varying(20),
    nomor_umi character varying(50),
    asal_kota character varying(100),
    is_luar_kota boolean DEFAULT false,
    status_verifikasi character varying(30) DEFAULT 'pending'::character varying,
    tanggal_daftar date DEFAULT CURRENT_DATE,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);


ALTER TABLE public.ppdb_pendaftar OWNER TO postgres;

--
-- Name: ppdb_pendaftar_id_pendaftaran_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.ppdb_pendaftar_id_pendaftaran_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.ppdb_pendaftar_id_pendaftaran_seq OWNER TO postgres;

--
-- Name: ppdb_pendaftar_id_pendaftaran_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.ppdb_pendaftar_id_pendaftaran_seq OWNED BY public.ppdb_pendaftar.id_pendaftaran;


--
-- Name: ppdb_tes; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.ppdb_tes (
    id_tes integer NOT NULL,
    id_pendaftaran integer,
    nilai numeric,
    status_tes character varying(30),
    catatan text
);


ALTER TABLE public.ppdb_tes OWNER TO postgres;

--
-- Name: ppdb_tes_id_tes_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.ppdb_tes_id_tes_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.ppdb_tes_id_tes_seq OWNER TO postgres;

--
-- Name: ppdb_tes_id_tes_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.ppdb_tes_id_tes_seq OWNED BY public.ppdb_tes.id_tes;


--
-- Name: ppdb_verifikasi; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.ppdb_verifikasi (
    id_verif integer NOT NULL,
    id_pendaftaran integer,
    id_petugas integer,
    tanggal_verif timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    hasil character varying(20),
    catatan text
);


ALTER TABLE public.ppdb_verifikasi OWNER TO postgres;

--
-- Name: ppdb_verifikasi_id_verif_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.ppdb_verifikasi_id_verif_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.ppdb_verifikasi_id_verif_seq OWNER TO postgres;

--
-- Name: ppdb_verifikasi_id_verif_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.ppdb_verifikasi_id_verif_seq OWNED BY public.ppdb_verifikasi.id_verif;


--
-- Name: sesi_absensi; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.sesi_absensi (
    id_sesi integer NOT NULL,
    id_jadwal integer NOT NULL,
    id_petugas_hadir integer NOT NULL,
    id_petugas_pengganti integer,
    tanggal date NOT NULL,
    waktu_mulai time without time zone,
    waktu_selesai time without time zone,
    status_sesi character varying(20) DEFAULT 'BERLANGSUNG'::character varying,
    keterangan text,
    is_validated boolean DEFAULT false,
    validated_by integer,
    validated_at timestamp without time zone,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);


ALTER TABLE public.sesi_absensi OWNER TO postgres;

--
-- Name: sesi_absensi_id_sesi_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.sesi_absensi_id_sesi_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.sesi_absensi_id_sesi_seq OWNER TO postgres;

--
-- Name: sesi_absensi_id_sesi_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.sesi_absensi_id_sesi_seq OWNED BY public.sesi_absensi.id_sesi;


--
-- Name: spp_setting; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.spp_setting (
    id_setting integer NOT NULL,
    id_unit integer,
    jenjang character varying(20),
    kategori_tagihan_id integer,
    jumlah numeric,
    periode character varying(20),
    keterangan text
);


ALTER TABLE public.spp_setting OWNER TO postgres;

--
-- Name: spp_setting_id_setting_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.spp_setting_id_setting_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.spp_setting_id_setting_seq OWNER TO postgres;

--
-- Name: spp_setting_id_setting_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.spp_setting_id_setting_seq OWNED BY public.spp_setting.id_setting;


--
-- Name: vw_santri_lengkap; Type: VIEW; Schema: public; Owner: postgres
--

CREATE VIEW public.vw_santri_lengkap AS
 SELECT ds.id_santri,
    ds.nomor_induk,
    ds.nama_lengkap_santri,
    ds.jenis_kelamin,
    ds.tempat_lahir,
    ds.tanggal_lahir,
    ds.alamat_tinggal,
    ds.nomor_telepon,
    ds.alamat_email,
    dk.kode_kelas,
    dk.nama_kelas,
    dk.nama_jurusan,
    dk.tahun_ajaran,
    du.kode_unit,
    du.nama_unit,
    ds.status
   FROM ((public.data_santri ds
     JOIN public.data_kelas dk ON (((ds.kode_kelas)::text = (dk.kode_kelas)::text)))
     JOIN public.data_unit du ON (((dk.kode_unit)::text = (du.kode_unit)::text)));


ALTER VIEW public.vw_santri_lengkap OWNER TO postgres;

--
-- Name: VIEW vw_santri_lengkap; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON VIEW public.vw_santri_lengkap IS 'Data santri lengkap dengan informasi kelas dan unit';


--
-- Name: vw_tahun_ajaran_summary; Type: VIEW; Schema: public; Owner: postgres
--

CREATE VIEW public.vw_tahun_ajaran_summary AS
 SELECT ta.id_tahun_ajaran,
    ta.kode_tahun,
    ta.nama_tahun,
    ta.status,
    count(DISTINCT dk.id_kelas) AS jumlah_kelas,
    count(DISTINCT ds.id_santri) AS jumlah_santri
   FROM ((public.data_tahun_ajaran ta
     LEFT JOIN public.data_kelas dk ON (((ta.nama_tahun)::text = (dk.tahun_ajaran)::text)))
     LEFT JOIN public.data_santri ds ON (((dk.kode_kelas)::text = (ds.kode_kelas)::text)))
  WHERE (ta.is_deleted = false)
  GROUP BY ta.id_tahun_ajaran, ta.kode_tahun, ta.nama_tahun, ta.status;


ALTER VIEW public.vw_tahun_ajaran_summary OWNER TO postgres;

--
-- Name: VIEW vw_tahun_ajaran_summary; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON VIEW public.vw_tahun_ajaran_summary IS 'Summary tahun ajaran dengan jumlah kelas dan santri';


--
-- Name: absensi_pengajar id_abs_pengajar; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.absensi_pengajar ALTER COLUMN id_abs_pengajar SET DEFAULT nextval('public.absensi_pengajar_id_abs_pengajar_seq'::regclass);


--
-- Name: absensi_santri id_absensi; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.absensi_santri ALTER COLUMN id_absensi SET DEFAULT nextval('public.absensi_santri_id_absensi_seq'::regclass);


--
-- Name: administrasi_bebas id_admin_bebas; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.administrasi_bebas ALTER COLUMN id_admin_bebas SET DEFAULT nextval('public.administrasi_bebas_id_admin_bebas_seq'::regclass);


--
-- Name: akun_pendaftar id_akun; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.akun_pendaftar ALTER COLUMN id_akun SET DEFAULT nextval('public.akun_pendaftar_id_akun_seq'::regclass);


--
-- Name: data_akun_santri id_akun_santri; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.data_akun_santri ALTER COLUMN id_akun_santri SET DEFAULT nextval('public.data_akun_santri_id_akun_santri_seq'::regclass);


--
-- Name: data_kategori_tagihan id_kategori; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.data_kategori_tagihan ALTER COLUMN id_kategori SET DEFAULT nextval('public.data_kategori_tagihan_id_kategori_seq'::regclass);


--
-- Name: data_kelas id_kelas; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.data_kelas ALTER COLUMN id_kelas SET DEFAULT nextval('public.data_kelas_id_kelas_seq'::regclass);


--
-- Name: data_kelas_mapel id_kelas_mapel; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.data_kelas_mapel ALTER COLUMN id_kelas_mapel SET DEFAULT nextval('public.data_kelas_mapel_id_kelas_mapel_seq'::regclass);


--
-- Name: data_konversi_nilai id_konversi; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.data_konversi_nilai ALTER COLUMN id_konversi SET DEFAULT nextval('public.data_konversi_nilai_id_konversi_seq'::regclass);


--
-- Name: data_mata_pelajaran id_mapel; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.data_mata_pelajaran ALTER COLUMN id_mapel SET DEFAULT nextval('public.data_mata_pelajaran_id_mapel_seq'::regclass);


--
-- Name: data_nilai_siswa id_nilai; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.data_nilai_siswa ALTER COLUMN id_nilai SET DEFAULT nextval('public.data_nilai_siswa_id_nilai_seq'::regclass);


--
-- Name: data_petugas id_petugas; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.data_petugas ALTER COLUMN id_petugas SET DEFAULT nextval('public.data_petugas_id_petugas_seq'::regclass);


--
-- Name: data_raport id_raport; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.data_raport ALTER COLUMN id_raport SET DEFAULT nextval('public.data_raport_id_raport_seq'::regclass);


--
-- Name: data_rekening_bank id_rekening; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.data_rekening_bank ALTER COLUMN id_rekening SET DEFAULT nextval('public.data_rekening_bank_id_rekening_seq'::regclass);


--
-- Name: data_santri id_santri; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.data_santri ALTER COLUMN id_santri SET DEFAULT nextval('public.data_santri_id_santri_seq'::regclass);


--
-- Name: data_tahun_ajaran id_tahun_ajaran; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.data_tahun_ajaran ALTER COLUMN id_tahun_ajaran SET DEFAULT nextval('public.data_tahun_ajaran_id_tahun_ajaran_seq'::regclass);


--
-- Name: data_unit id_unit; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.data_unit ALTER COLUMN id_unit SET DEFAULT nextval('public.data_unit_id_unit_seq'::regclass);


--
-- Name: jadwal_pembelajaran id_jadwal; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.jadwal_pembelajaran ALTER COLUMN id_jadwal SET DEFAULT nextval('public.jadwal_pembelajaran_id_jadwal_seq'::regclass);


--
-- Name: kwitansi_pdf id_kwitansi; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.kwitansi_pdf ALTER COLUMN id_kwitansi SET DEFAULT nextval('public.kwitansi_pdf_id_kwitansi_seq'::regclass);


--
-- Name: log_aktivitas id_log_aktivitas; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.log_aktivitas ALTER COLUMN id_log_aktivitas SET DEFAULT nextval('public.log_aktivitas_id_log_aktivitas_seq'::regclass);


--
-- Name: log_download_raport id_log; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.log_download_raport ALTER COLUMN id_log SET DEFAULT nextval('public.log_download_raport_id_log_seq'::regclass);


--
-- Name: log_perubahan_absensi id_log; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.log_perubahan_absensi ALTER COLUMN id_log SET DEFAULT nextval('public.log_perubahan_absensi_id_log_seq'::regclass);


--
-- Name: pembayaran_spp id_pembayaran; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.pembayaran_spp ALTER COLUMN id_pembayaran SET DEFAULT nextval('public.pembayaran_spp_id_pembayaran_seq'::regclass);


--
-- Name: ppdb_berkas id_berkas; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.ppdb_berkas ALTER COLUMN id_berkas SET DEFAULT nextval('public.ppdb_berkas_id_berkas_seq'::regclass);


--
-- Name: ppdb_notifikasi id_notif; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.ppdb_notifikasi ALTER COLUMN id_notif SET DEFAULT nextval('public.ppdb_notifikasi_id_notif_seq'::regclass);


--
-- Name: ppdb_pendaftar id_pendaftaran; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.ppdb_pendaftar ALTER COLUMN id_pendaftaran SET DEFAULT nextval('public.ppdb_pendaftar_id_pendaftaran_seq'::regclass);


--
-- Name: ppdb_tes id_tes; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.ppdb_tes ALTER COLUMN id_tes SET DEFAULT nextval('public.ppdb_tes_id_tes_seq'::regclass);


--
-- Name: ppdb_verifikasi id_verif; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.ppdb_verifikasi ALTER COLUMN id_verif SET DEFAULT nextval('public.ppdb_verifikasi_id_verif_seq'::regclass);


--
-- Name: sesi_absensi id_sesi; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.sesi_absensi ALTER COLUMN id_sesi SET DEFAULT nextval('public.sesi_absensi_id_sesi_seq'::regclass);


--
-- Name: spp_setting id_setting; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.spp_setting ALTER COLUMN id_setting SET DEFAULT nextval('public.spp_setting_id_setting_seq'::regclass);


--
-- Name: absensi_pengajar absensi_pengajar_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.absensi_pengajar
    ADD CONSTRAINT absensi_pengajar_pkey PRIMARY KEY (id_abs_pengajar);


--
-- Name: absensi_santri absensi_santri_id_sesi_nomor_induk_key; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.absensi_santri
    ADD CONSTRAINT absensi_santri_id_sesi_nomor_induk_key UNIQUE (id_sesi, nomor_induk);


--
-- Name: absensi_santri absensi_santri_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.absensi_santri
    ADD CONSTRAINT absensi_santri_pkey PRIMARY KEY (id_absensi);


--
-- Name: administrasi_bebas administrasi_bebas_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.administrasi_bebas
    ADD CONSTRAINT administrasi_bebas_pkey PRIMARY KEY (id_admin_bebas);


--
-- Name: akun_pendaftar akun_pendaftar_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.akun_pendaftar
    ADD CONSTRAINT akun_pendaftar_pkey PRIMARY KEY (id_akun);


--
-- Name: data_akun_santri data_akun_santri_nomor_induk_key; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.data_akun_santri
    ADD CONSTRAINT data_akun_santri_nomor_induk_key UNIQUE (nomor_induk);


--
-- Name: data_akun_santri data_akun_santri_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.data_akun_santri
    ADD CONSTRAINT data_akun_santri_pkey PRIMARY KEY (id_akun_santri);


--
-- Name: data_kategori_tagihan data_kategori_tagihan_kode_kategori_key; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.data_kategori_tagihan
    ADD CONSTRAINT data_kategori_tagihan_kode_kategori_key UNIQUE (kode_kategori);


--
-- Name: data_kategori_tagihan data_kategori_tagihan_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.data_kategori_tagihan
    ADD CONSTRAINT data_kategori_tagihan_pkey PRIMARY KEY (id_kategori);


--
-- Name: data_kelas data_kelas_kode_kelas_key; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.data_kelas
    ADD CONSTRAINT data_kelas_kode_kelas_key UNIQUE (kode_kelas);


--
-- Name: data_kelas_mapel data_kelas_mapel_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.data_kelas_mapel
    ADD CONSTRAINT data_kelas_mapel_pkey PRIMARY KEY (id_kelas_mapel);


--
-- Name: data_kelas data_kelas_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.data_kelas
    ADD CONSTRAINT data_kelas_pkey PRIMARY KEY (id_kelas);


--
-- Name: data_konversi_nilai data_konversi_nilai_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.data_konversi_nilai
    ADD CONSTRAINT data_konversi_nilai_pkey PRIMARY KEY (id_konversi);


--
-- Name: data_mata_pelajaran data_mata_pelajaran_kode_mapel_key; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.data_mata_pelajaran
    ADD CONSTRAINT data_mata_pelajaran_kode_mapel_key UNIQUE (kode_mapel);


--
-- Name: data_mata_pelajaran data_mata_pelajaran_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.data_mata_pelajaran
    ADD CONSTRAINT data_mata_pelajaran_pkey PRIMARY KEY (id_mapel);


--
-- Name: data_nilai_siswa data_nilai_siswa_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.data_nilai_siswa
    ADD CONSTRAINT data_nilai_siswa_pkey PRIMARY KEY (id_nilai);


--
-- Name: data_petugas data_petugas_alamat_email_key; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.data_petugas
    ADD CONSTRAINT data_petugas_alamat_email_key UNIQUE (alamat_email);


--
-- Name: data_petugas data_petugas_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.data_petugas
    ADD CONSTRAINT data_petugas_pkey PRIMARY KEY (id_petugas);


--
-- Name: data_raport data_raport_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.data_raport
    ADD CONSTRAINT data_raport_pkey PRIMARY KEY (id_raport);


--
-- Name: data_rekening_bank data_rekening_bank_kode_rekening_key; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.data_rekening_bank
    ADD CONSTRAINT data_rekening_bank_kode_rekening_key UNIQUE (kode_rekening);


--
-- Name: data_rekening_bank data_rekening_bank_nomor_rekening_key; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.data_rekening_bank
    ADD CONSTRAINT data_rekening_bank_nomor_rekening_key UNIQUE (nomor_rekening);


--
-- Name: data_rekening_bank data_rekening_bank_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.data_rekening_bank
    ADD CONSTRAINT data_rekening_bank_pkey PRIMARY KEY (id_rekening);


--
-- Name: data_santri data_santri_nomor_induk_key; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.data_santri
    ADD CONSTRAINT data_santri_nomor_induk_key UNIQUE (nomor_induk);


--
-- Name: data_santri data_santri_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.data_santri
    ADD CONSTRAINT data_santri_pkey PRIMARY KEY (id_santri);


--
-- Name: data_tahun_ajaran data_tahun_ajaran_kode_tahun_key; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.data_tahun_ajaran
    ADD CONSTRAINT data_tahun_ajaran_kode_tahun_key UNIQUE (kode_tahun);


--
-- Name: data_tahun_ajaran data_tahun_ajaran_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.data_tahun_ajaran
    ADD CONSTRAINT data_tahun_ajaran_pkey PRIMARY KEY (id_tahun_ajaran);


--
-- Name: data_unit data_unit_kode_unit_key; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.data_unit
    ADD CONSTRAINT data_unit_kode_unit_key UNIQUE (kode_unit);


--
-- Name: data_unit data_unit_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.data_unit
    ADD CONSTRAINT data_unit_pkey PRIMARY KEY (id_unit);


--
-- Name: jadwal_pembelajaran jadwal_pembelajaran_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.jadwal_pembelajaran
    ADD CONSTRAINT jadwal_pembelajaran_pkey PRIMARY KEY (id_jadwal);


--
-- Name: kwitansi_pdf kwitansi_pdf_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.kwitansi_pdf
    ADD CONSTRAINT kwitansi_pdf_pkey PRIMARY KEY (id_kwitansi);


--
-- Name: log_aktivitas log_aktivitas_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.log_aktivitas
    ADD CONSTRAINT log_aktivitas_pkey PRIMARY KEY (id_log_aktivitas);


--
-- Name: log_download_raport log_download_raport_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.log_download_raport
    ADD CONSTRAINT log_download_raport_pkey PRIMARY KEY (id_log);


--
-- Name: log_perubahan_absensi log_perubahan_absensi_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.log_perubahan_absensi
    ADD CONSTRAINT log_perubahan_absensi_pkey PRIMARY KEY (id_log);


--
-- Name: pembayaran_spp pembayaran_spp_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.pembayaran_spp
    ADD CONSTRAINT pembayaran_spp_pkey PRIMARY KEY (id_pembayaran);


--
-- Name: ppdb_berkas ppdb_berkas_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.ppdb_berkas
    ADD CONSTRAINT ppdb_berkas_pkey PRIMARY KEY (id_berkas);


--
-- Name: ppdb_notifikasi ppdb_notifikasi_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.ppdb_notifikasi
    ADD CONSTRAINT ppdb_notifikasi_pkey PRIMARY KEY (id_notif);


--
-- Name: ppdb_pendaftar ppdb_pendaftar_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.ppdb_pendaftar
    ADD CONSTRAINT ppdb_pendaftar_pkey PRIMARY KEY (id_pendaftaran);


--
-- Name: ppdb_tes ppdb_tes_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.ppdb_tes
    ADD CONSTRAINT ppdb_tes_pkey PRIMARY KEY (id_tes);


--
-- Name: ppdb_verifikasi ppdb_verifikasi_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.ppdb_verifikasi
    ADD CONSTRAINT ppdb_verifikasi_pkey PRIMARY KEY (id_verif);


--
-- Name: sesi_absensi sesi_absensi_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.sesi_absensi
    ADD CONSTRAINT sesi_absensi_pkey PRIMARY KEY (id_sesi);


--
-- Name: spp_setting spp_setting_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.spp_setting
    ADD CONSTRAINT spp_setting_pkey PRIMARY KEY (id_setting);


--
-- Name: data_kelas_mapel uq_kelas_mapel_semester; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.data_kelas_mapel
    ADD CONSTRAINT uq_kelas_mapel_semester UNIQUE (kode_kelas, kode_mapel, tahun_ajaran, semester);


--
-- Name: data_nilai_siswa uq_nilai_siswa; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.data_nilai_siswa
    ADD CONSTRAINT uq_nilai_siswa UNIQUE (nomor_induk, kode_mapel, tahun_ajaran, semester);


--
-- Name: data_raport uq_raport; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.data_raport
    ADD CONSTRAINT uq_raport UNIQUE (nomor_induk, tahun_ajaran, semester);


--
-- Name: idx_administrasi_bebas_santri; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_administrasi_bebas_santri ON public.administrasi_bebas USING btree (id_santri);


--
-- Name: idx_akun_pendaftar_email; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_akun_pendaftar_email ON public.akun_pendaftar USING btree (email);


--
-- Name: idx_akun_santri_nama_akun; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_akun_santri_nama_akun ON public.data_akun_santri USING btree (nama_akun);


--
-- Name: idx_akun_santri_nomor_induk; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_akun_santri_nomor_induk ON public.data_akun_santri USING btree (nomor_induk);


--
-- Name: idx_kategori_kode; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_kategori_kode ON public.data_kategori_tagihan USING btree (kode_kategori);


--
-- Name: idx_kategori_unit; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_kategori_unit ON public.data_kategori_tagihan USING btree (pilihan_unit);


--
-- Name: idx_kelas_tahun; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_kelas_tahun ON public.data_kelas USING btree (tahun_ajaran);


--
-- Name: idx_kelas_unit; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_kelas_unit ON public.data_kelas USING btree (kode_unit);


--
-- Name: idx_kwitansi_pembayaran; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_kwitansi_pembayaran ON public.kwitansi_pdf USING btree (id_pembayaran);


--
-- Name: idx_pembayaran_spp_santri; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_pembayaran_spp_santri ON public.pembayaran_spp USING btree (id_santri);


--
-- Name: idx_pembayaran_spp_setting; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_pembayaran_spp_setting ON public.pembayaran_spp USING btree (id_setting);


--
-- Name: idx_pembayaran_spp_tanggal; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_pembayaran_spp_tanggal ON public.pembayaran_spp USING btree (tanggal_bayar);


--
-- Name: idx_petugas_email; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_petugas_email ON public.data_petugas USING btree (alamat_email);


--
-- Name: idx_ppdb_berkas_pendaftaran; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_ppdb_berkas_pendaftaran ON public.ppdb_berkas USING btree (id_pendaftaran);


--
-- Name: idx_ppdb_notifikasi_pendaftaran; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_ppdb_notifikasi_pendaftaran ON public.ppdb_notifikasi USING btree (id_pendaftaran);


--
-- Name: idx_ppdb_pendaftar_akun; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_ppdb_pendaftar_akun ON public.ppdb_pendaftar USING btree (id_akun);


--
-- Name: idx_ppdb_pendaftar_no; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_ppdb_pendaftar_no ON public.ppdb_pendaftar USING btree (no_pendaftaran);


--
-- Name: idx_ppdb_pendaftar_status; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_ppdb_pendaftar_status ON public.ppdb_pendaftar USING btree (status_verifikasi);


--
-- Name: idx_ppdb_tes_pendaftaran; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_ppdb_tes_pendaftaran ON public.ppdb_tes USING btree (id_pendaftaran);


--
-- Name: idx_ppdb_verifikasi_pendaftaran; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_ppdb_verifikasi_pendaftaran ON public.ppdb_verifikasi USING btree (id_pendaftaran);


--
-- Name: idx_rekening_nomor; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_rekening_nomor ON public.data_rekening_bank USING btree (nomor_rekening);


--
-- Name: idx_rekening_unit; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_rekening_unit ON public.data_rekening_bank USING btree (kode_unit);


--
-- Name: idx_santri_kelas; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_santri_kelas ON public.data_santri USING btree (kode_kelas);


--
-- Name: idx_santri_nama; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_santri_nama ON public.data_santri USING btree (nama_lengkap_santri);


--
-- Name: idx_santri_nomor_induk; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_santri_nomor_induk ON public.data_santri USING btree (nomor_induk);


--
-- Name: idx_spp_setting_kategori; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_spp_setting_kategori ON public.spp_setting USING btree (kategori_tagihan_id);


--
-- Name: idx_spp_setting_unit; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_spp_setting_unit ON public.spp_setting USING btree (id_unit);


--
-- Name: absensi_santri trg_absensi_santri_updated_at; Type: TRIGGER; Schema: public; Owner: postgres
--

CREATE TRIGGER trg_absensi_santri_updated_at BEFORE UPDATE ON public.absensi_santri FOR EACH ROW EXECUTE FUNCTION public.update_updated_at_column();


--
-- Name: absensi_santri trg_audit_absensi_santri; Type: TRIGGER; Schema: public; Owner: postgres
--

CREATE TRIGGER trg_audit_absensi_santri AFTER UPDATE ON public.absensi_santri FOR EACH ROW EXECUTE FUNCTION public.catat_log_absensi_santri();


--
-- Name: jadwal_pembelajaran trg_jadwal_updated_at; Type: TRIGGER; Schema: public; Owner: postgres
--

CREATE TRIGGER trg_jadwal_updated_at BEFORE UPDATE ON public.jadwal_pembelajaran FOR EACH ROW EXECUTE FUNCTION public.update_updated_at_column();


--
-- Name: akun_pendaftar update_akun_pendaftar_updated_at; Type: TRIGGER; Schema: public; Owner: postgres
--

CREATE TRIGGER update_akun_pendaftar_updated_at BEFORE UPDATE ON public.akun_pendaftar FOR EACH ROW EXECUTE FUNCTION public.update_updated_at_column();


--
-- Name: data_akun_santri update_data_akun_santri_updated_at; Type: TRIGGER; Schema: public; Owner: postgres
--

CREATE TRIGGER update_data_akun_santri_updated_at BEFORE UPDATE ON public.data_akun_santri FOR EACH ROW EXECUTE FUNCTION public.update_updated_at_column();


--
-- Name: data_kategori_tagihan update_data_kategori_tagihan_updated_at; Type: TRIGGER; Schema: public; Owner: postgres
--

CREATE TRIGGER update_data_kategori_tagihan_updated_at BEFORE UPDATE ON public.data_kategori_tagihan FOR EACH ROW EXECUTE FUNCTION public.update_updated_at_column();


--
-- Name: data_kelas update_data_kelas_updated_at; Type: TRIGGER; Schema: public; Owner: postgres
--

CREATE TRIGGER update_data_kelas_updated_at BEFORE UPDATE ON public.data_kelas FOR EACH ROW EXECUTE FUNCTION public.update_updated_at_column();


--
-- Name: data_petugas update_data_petugas_updated_at; Type: TRIGGER; Schema: public; Owner: postgres
--

CREATE TRIGGER update_data_petugas_updated_at BEFORE UPDATE ON public.data_petugas FOR EACH ROW EXECUTE FUNCTION public.update_updated_at_column();


--
-- Name: data_rekening_bank update_data_rekening_bank_updated_at; Type: TRIGGER; Schema: public; Owner: postgres
--

CREATE TRIGGER update_data_rekening_bank_updated_at BEFORE UPDATE ON public.data_rekening_bank FOR EACH ROW EXECUTE FUNCTION public.update_updated_at_column();


--
-- Name: data_santri update_data_santri_updated_at; Type: TRIGGER; Schema: public; Owner: postgres
--

CREATE TRIGGER update_data_santri_updated_at BEFORE UPDATE ON public.data_santri FOR EACH ROW EXECUTE FUNCTION public.update_updated_at_column();


--
-- Name: data_tahun_ajaran update_data_tahun_ajaran_updated_at; Type: TRIGGER; Schema: public; Owner: postgres
--

CREATE TRIGGER update_data_tahun_ajaran_updated_at BEFORE UPDATE ON public.data_tahun_ajaran FOR EACH ROW EXECUTE FUNCTION public.update_updated_at_column();


--
-- Name: data_unit update_data_unit_updated_at; Type: TRIGGER; Schema: public; Owner: postgres
--

CREATE TRIGGER update_data_unit_updated_at BEFORE UPDATE ON public.data_unit FOR EACH ROW EXECUTE FUNCTION public.update_updated_at_column();


--
-- Name: absensi_pengajar absensi_pengajar_id_petugas_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.absensi_pengajar
    ADD CONSTRAINT absensi_pengajar_id_petugas_fkey FOREIGN KEY (id_petugas) REFERENCES public.data_petugas(id_petugas);


--
-- Name: absensi_pengajar absensi_pengajar_id_sesi_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.absensi_pengajar
    ADD CONSTRAINT absensi_pengajar_id_sesi_fkey FOREIGN KEY (id_sesi) REFERENCES public.sesi_absensi(id_sesi);


--
-- Name: absensi_pengajar absensi_pengajar_input_oleh_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.absensi_pengajar
    ADD CONSTRAINT absensi_pengajar_input_oleh_fkey FOREIGN KEY (input_oleh) REFERENCES public.data_petugas(id_petugas);


--
-- Name: absensi_santri absensi_santri_id_sesi_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.absensi_santri
    ADD CONSTRAINT absensi_santri_id_sesi_fkey FOREIGN KEY (id_sesi) REFERENCES public.sesi_absensi(id_sesi);


--
-- Name: absensi_santri absensi_santri_input_oleh_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.absensi_santri
    ADD CONSTRAINT absensi_santri_input_oleh_fkey FOREIGN KEY (input_oleh) REFERENCES public.data_petugas(id_petugas);


--
-- Name: absensi_santri absensi_santri_nomor_induk_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.absensi_santri
    ADD CONSTRAINT absensi_santri_nomor_induk_fkey FOREIGN KEY (nomor_induk) REFERENCES public.data_santri(nomor_induk) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: administrasi_bebas fk_administrasi_bebas_santri; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.administrasi_bebas
    ADD CONSTRAINT fk_administrasi_bebas_santri FOREIGN KEY (id_santri) REFERENCES public.data_santri(id_santri);


--
-- Name: data_akun_santri fk_akun_santri; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.data_akun_santri
    ADD CONSTRAINT fk_akun_santri FOREIGN KEY (nomor_induk) REFERENCES public.data_santri(nomor_induk) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: data_kelas fk_kelas_unit; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.data_kelas
    ADD CONSTRAINT fk_kelas_unit FOREIGN KEY (kode_unit) REFERENCES public.data_unit(kode_unit) ON UPDATE CASCADE ON DELETE RESTRICT;


--
-- Name: data_kelas_mapel fk_km_kelas; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.data_kelas_mapel
    ADD CONSTRAINT fk_km_kelas FOREIGN KEY (kode_kelas) REFERENCES public.data_kelas(kode_kelas) ON UPDATE CASCADE ON DELETE RESTRICT;


--
-- Name: data_kelas_mapel fk_km_mapel; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.data_kelas_mapel
    ADD CONSTRAINT fk_km_mapel FOREIGN KEY (kode_mapel) REFERENCES public.data_mata_pelajaran(kode_mapel) ON UPDATE CASCADE ON DELETE RESTRICT;


--
-- Name: data_kelas_mapel fk_km_petugas; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.data_kelas_mapel
    ADD CONSTRAINT fk_km_petugas FOREIGN KEY (id_petugas) REFERENCES public.data_petugas(id_petugas) ON UPDATE CASCADE ON DELETE SET NULL;


--
-- Name: data_konversi_nilai fk_konversi_unit; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.data_konversi_nilai
    ADD CONSTRAINT fk_konversi_unit FOREIGN KEY (kode_unit) REFERENCES public.data_unit(kode_unit) ON UPDATE CASCADE ON DELETE SET NULL;


--
-- Name: kwitansi_pdf fk_kwitansi_pembayaran; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.kwitansi_pdf
    ADD CONSTRAINT fk_kwitansi_pembayaran FOREIGN KEY (id_pembayaran) REFERENCES public.pembayaran_spp(id_pembayaran);


--
-- Name: kwitansi_pdf fk_kwitansi_petugas; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.kwitansi_pdf
    ADD CONSTRAINT fk_kwitansi_petugas FOREIGN KEY (id_petugas) REFERENCES public.data_petugas(id_petugas);


--
-- Name: log_download_raport fk_log_petugas; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.log_download_raport
    ADD CONSTRAINT fk_log_petugas FOREIGN KEY (id_petugas) REFERENCES public.data_petugas(id_petugas) ON UPDATE CASCADE ON DELETE SET NULL;


--
-- Name: log_download_raport fk_log_raport; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.log_download_raport
    ADD CONSTRAINT fk_log_raport FOREIGN KEY (id_raport) REFERENCES public.data_raport(id_raport) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: log_download_raport fk_log_santri; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.log_download_raport
    ADD CONSTRAINT fk_log_santri FOREIGN KEY (nomor_induk) REFERENCES public.data_santri(nomor_induk) ON UPDATE CASCADE ON DELETE SET NULL;


--
-- Name: data_mata_pelajaran fk_mapel_unit; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.data_mata_pelajaran
    ADD CONSTRAINT fk_mapel_unit FOREIGN KEY (kode_unit) REFERENCES public.data_unit(kode_unit) ON UPDATE CASCADE ON DELETE RESTRICT;


--
-- Name: data_nilai_siswa fk_nilai_kelas; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.data_nilai_siswa
    ADD CONSTRAINT fk_nilai_kelas FOREIGN KEY (kode_kelas) REFERENCES public.data_kelas(kode_kelas) ON UPDATE CASCADE ON DELETE RESTRICT;


--
-- Name: data_nilai_siswa fk_nilai_mapel; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.data_nilai_siswa
    ADD CONSTRAINT fk_nilai_mapel FOREIGN KEY (kode_mapel) REFERENCES public.data_mata_pelajaran(kode_mapel) ON UPDATE CASCADE ON DELETE RESTRICT;


--
-- Name: data_nilai_siswa fk_nilai_petugas; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.data_nilai_siswa
    ADD CONSTRAINT fk_nilai_petugas FOREIGN KEY (id_petugas_input) REFERENCES public.data_petugas(id_petugas) ON UPDATE CASCADE ON DELETE SET NULL;


--
-- Name: data_nilai_siswa fk_nilai_santri; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.data_nilai_siswa
    ADD CONSTRAINT fk_nilai_santri FOREIGN KEY (nomor_induk) REFERENCES public.data_santri(nomor_induk) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: pembayaran_spp fk_pembayaran_spp_rekening; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.pembayaran_spp
    ADD CONSTRAINT fk_pembayaran_spp_rekening FOREIGN KEY (id_rekening) REFERENCES public.data_rekening_bank(id_rekening);


--
-- Name: pembayaran_spp fk_pembayaran_spp_santri; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.pembayaran_spp
    ADD CONSTRAINT fk_pembayaran_spp_santri FOREIGN KEY (id_santri) REFERENCES public.data_santri(id_santri);


--
-- Name: pembayaran_spp fk_pembayaran_spp_setting; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.pembayaran_spp
    ADD CONSTRAINT fk_pembayaran_spp_setting FOREIGN KEY (id_setting) REFERENCES public.spp_setting(id_setting);


--
-- Name: ppdb_berkas fk_ppdb_berkas_pendaftaran; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.ppdb_berkas
    ADD CONSTRAINT fk_ppdb_berkas_pendaftaran FOREIGN KEY (id_pendaftaran) REFERENCES public.ppdb_pendaftar(id_pendaftaran) ON DELETE CASCADE;


--
-- Name: ppdb_notifikasi fk_ppdb_notifikasi_pendaftaran; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.ppdb_notifikasi
    ADD CONSTRAINT fk_ppdb_notifikasi_pendaftaran FOREIGN KEY (id_pendaftaran) REFERENCES public.ppdb_pendaftar(id_pendaftaran);


--
-- Name: ppdb_pendaftar fk_ppdb_pendaftar_akun; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.ppdb_pendaftar
    ADD CONSTRAINT fk_ppdb_pendaftar_akun FOREIGN KEY (id_akun) REFERENCES public.akun_pendaftar(id_akun);


--
-- Name: ppdb_tes fk_ppdb_tes_pendaftaran; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.ppdb_tes
    ADD CONSTRAINT fk_ppdb_tes_pendaftaran FOREIGN KEY (id_pendaftaran) REFERENCES public.ppdb_pendaftar(id_pendaftaran) ON DELETE CASCADE;


--
-- Name: ppdb_verifikasi fk_ppdb_verifikasi_pendaftaran; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.ppdb_verifikasi
    ADD CONSTRAINT fk_ppdb_verifikasi_pendaftaran FOREIGN KEY (id_pendaftaran) REFERENCES public.ppdb_pendaftar(id_pendaftaran);


--
-- Name: ppdb_verifikasi fk_ppdb_verifikasi_petugas; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.ppdb_verifikasi
    ADD CONSTRAINT fk_ppdb_verifikasi_petugas FOREIGN KEY (id_petugas) REFERENCES public.data_petugas(id_petugas);


--
-- Name: data_raport fk_raport_kelas; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.data_raport
    ADD CONSTRAINT fk_raport_kelas FOREIGN KEY (kode_kelas) REFERENCES public.data_kelas(kode_kelas) ON UPDATE CASCADE ON DELETE RESTRICT;


--
-- Name: data_raport fk_raport_santri; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.data_raport
    ADD CONSTRAINT fk_raport_santri FOREIGN KEY (nomor_induk) REFERENCES public.data_santri(nomor_induk) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: data_raport fk_raport_wali; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.data_raport
    ADD CONSTRAINT fk_raport_wali FOREIGN KEY (id_wali_kelas) REFERENCES public.data_petugas(id_petugas) ON UPDATE CASCADE ON DELETE SET NULL;


--
-- Name: data_rekening_bank fk_rekening_unit; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.data_rekening_bank
    ADD CONSTRAINT fk_rekening_unit FOREIGN KEY (kode_unit) REFERENCES public.data_unit(kode_unit) ON UPDATE CASCADE ON DELETE RESTRICT;


--
-- Name: data_santri fk_santri_kelas; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.data_santri
    ADD CONSTRAINT fk_santri_kelas FOREIGN KEY (kode_kelas) REFERENCES public.data_kelas(kode_kelas) ON UPDATE CASCADE ON DELETE RESTRICT;


--
-- Name: spp_setting fk_spp_setting_kategori; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.spp_setting
    ADD CONSTRAINT fk_spp_setting_kategori FOREIGN KEY (kategori_tagihan_id) REFERENCES public.data_kategori_tagihan(id_kategori);


--
-- Name: spp_setting fk_spp_setting_unit; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.spp_setting
    ADD CONSTRAINT fk_spp_setting_unit FOREIGN KEY (id_unit) REFERENCES public.data_unit(id_unit);


--
-- Name: jadwal_pembelajaran jadwal_pembelajaran_id_kelas_mapel_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.jadwal_pembelajaran
    ADD CONSTRAINT jadwal_pembelajaran_id_kelas_mapel_fkey FOREIGN KEY (id_kelas_mapel) REFERENCES public.data_kelas_mapel(id_kelas_mapel);


--
-- Name: log_aktivitas log_aktivitas_id_petugas_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.log_aktivitas
    ADD CONSTRAINT log_aktivitas_id_petugas_fkey FOREIGN KEY (id_petugas) REFERENCES public.data_petugas(id_petugas);


--
-- Name: log_perubahan_absensi log_perubahan_absensi_diubah_oleh_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.log_perubahan_absensi
    ADD CONSTRAINT log_perubahan_absensi_diubah_oleh_fkey FOREIGN KEY (diubah_oleh) REFERENCES public.data_petugas(id_petugas);


--
-- Name: sesi_absensi sesi_absensi_id_jadwal_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.sesi_absensi
    ADD CONSTRAINT sesi_absensi_id_jadwal_fkey FOREIGN KEY (id_jadwal) REFERENCES public.jadwal_pembelajaran(id_jadwal);


--
-- Name: sesi_absensi sesi_absensi_id_petugas_hadir_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.sesi_absensi
    ADD CONSTRAINT sesi_absensi_id_petugas_hadir_fkey FOREIGN KEY (id_petugas_hadir) REFERENCES public.data_petugas(id_petugas);


--
-- Name: sesi_absensi sesi_absensi_id_petugas_pengganti_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.sesi_absensi
    ADD CONSTRAINT sesi_absensi_id_petugas_pengganti_fkey FOREIGN KEY (id_petugas_pengganti) REFERENCES public.data_petugas(id_petugas);


--
-- Name: sesi_absensi sesi_absensi_validated_by_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.sesi_absensi
    ADD CONSTRAINT sesi_absensi_validated_by_fkey FOREIGN KEY (validated_by) REFERENCES public.data_petugas(id_petugas);


--
-- PostgreSQL database dump complete
--

