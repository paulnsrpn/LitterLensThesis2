-- WARNING: This schema is for context only and is not meant to be run.
-- Table order and constraints may not be valid for execution.

CREATE TABLE public.activity_logs (
  log_id integer NOT NULL DEFAULT nextval('activity_logs_log_id_seq'::regclass),
  admin_id integer NOT NULL,
  action character varying NOT NULL,
  timestamp timestamp with time zone DEFAULT (now() AT TIME ZONE 'Asia/Manila'::text),
  admin_name character varying,
  affected_table character varying,
  description text,
  log_status character varying DEFAULT 'Success'::character varying,
  CONSTRAINT activity_logs_pkey PRIMARY KEY (log_id),
  CONSTRAINT activity_logs_admin_id_fkey FOREIGN KEY (admin_id) REFERENCES public.admin(admin_id)
);
CREATE TABLE public.admin (
  admin_id integer NOT NULL DEFAULT nextval('admin_admin_id_seq'::regclass),
  admin_name character varying NOT NULL,
  email character varying NOT NULL UNIQUE,
  password character varying NOT NULL,
  role character varying NOT NULL,
  profile_pic character varying,
  contact_number character varying,
  CONSTRAINT admin_pkey PRIMARY KEY (admin_id)
);
CREATE TABLE public.creeks (
  creek_id integer GENERATED ALWAYS AS IDENTITY NOT NULL,
  creek_name character varying NOT NULL UNIQUE,
  city character varying,
  barangay character varying,
  province character varying,
  coordinates text,
  CONSTRAINT creeks_pkey PRIMARY KEY (creek_id)
);
CREATE TABLE public.detections (
  detection_id integer NOT NULL DEFAULT nextval('detections_detection_id_seq'::regclass),
  image_id integer NOT NULL,
  littertype_id integer NOT NULL,
  date date NOT NULL DEFAULT ((CURRENT_TIMESTAMP AT TIME ZONE 'Asia/Manila'::text))::date,
  quantity integer NOT NULL,
  confidence_lvl numeric NOT NULL,
  detection_time time without time zone NOT NULL DEFAULT ((CURRENT_TIME + '08:00:00'::interval))::time without time zone,
  CONSTRAINT detections_pkey PRIMARY KEY (detection_id),
  CONSTRAINT detections_image_id_fkey FOREIGN KEY (image_id) REFERENCES public.images(image_id),
  CONSTRAINT detections_littertype_id_fkey FOREIGN KEY (littertype_id) REFERENCES public.litter_types(littertype_id)
);
CREATE TABLE public.images (
  image_id integer NOT NULL DEFAULT nextval('images_image_id_seq'::regclass),
  imagefile_name character varying NOT NULL,
  uploaded_by integer,
  longitude numeric NOT NULL,
  latitude numeric NOT NULL,
  creek_id integer,
  CONSTRAINT images_pkey PRIMARY KEY (image_id),
  CONSTRAINT images_uploaded_by_fkey FOREIGN KEY (uploaded_by) REFERENCES public.admin(admin_id),
  CONSTRAINT images_creek_id_fkey FOREIGN KEY (creek_id) REFERENCES public.creeks(creek_id)
);
CREATE TABLE public.litter_types (
  littertype_id integer NOT NULL DEFAULT nextval('litter_types_littertype_id_seq'::regclass),
  littertype_name character varying NOT NULL,
  CONSTRAINT litter_types_pkey PRIMARY KEY (littertype_id)
);
CREATE TABLE public.models (
  model_id integer NOT NULL DEFAULT nextval('models_model_id_seq'::regclass),
  model_name character varying NOT NULL,
  version character varying NOT NULL,
  accuracy numeric,
  uploaded_on timestamp with time zone DEFAULT (now() AT TIME ZONE 'Asia/Manila'::text),
  uploaded_by integer,
  model_filename text NOT NULL,
  status character varying DEFAULT 'Inactive'::character varying CHECK (status::text = ANY (ARRAY['Active'::character varying, 'Inactive'::character varying]::text[])),
  description text,
  CONSTRAINT models_pkey PRIMARY KEY (model_id),
  CONSTRAINT models_uploaded_by_fkey FOREIGN KEY (uploaded_by) REFERENCES public.admin(admin_id)
);
CREATE TABLE public.realtime_detections (
  realtime_id integer NOT NULL DEFAULT nextval('realtime_detections_realtime_id_seq'::regclass),
  admin_id integer,
  camera_name character varying NOT NULL,
  camera_status character varying DEFAULT 'Idle'::character varying,
  total_detections integer DEFAULT 0,
  top_detected_litter character varying,
  detection_speed numeric DEFAULT 0.00,
  detection_accuracy numeric DEFAULT 0.00,
  longitude numeric,
  latitude numeric,
  timestamp timestamp with time zone DEFAULT (now() + '08:00:00'::interval),
  date date DEFAULT ((CURRENT_TIMESTAMP AT TIME ZONE 'Asia/Manila'::text))::date,
  CONSTRAINT realtime_detections_pkey PRIMARY KEY (realtime_id),
  CONSTRAINT realtime_detections_admin_id_fkey FOREIGN KEY (admin_id) REFERENCES public.admin(admin_id)
);
CREATE TABLE public.realtime_litter_summary (
  summary_id integer NOT NULL DEFAULT nextval('realtime_litter_summary_summary_id_seq'::regclass),
  realtime_id integer NOT NULL,
  littertype_id integer NOT NULL,
  litter_count integer DEFAULT 0,
  CONSTRAINT realtime_litter_summary_pkey PRIMARY KEY (summary_id),
  CONSTRAINT realtime_litter_summary_realtime_id_fkey FOREIGN KEY (realtime_id) REFERENCES public.realtime_detections(realtime_id),
  CONSTRAINT realtime_litter_summary_littertype_id_fkey FOREIGN KEY (littertype_id) REFERENCES public.litter_types(littertype_id)
);
CREATE TABLE public.test_table (
  id integer NOT NULL DEFAULT nextval('test_table_id_seq'::regclass),
  name text NOT NULL,
  email text NOT NULL,
  CONSTRAINT test_table_pkey PRIMARY KEY (id)
);