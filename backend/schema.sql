CREATE TABLE IF NOT EXISTS products (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  type TEXT NOT NULL DEFAULT 'regular',
  name TEXT NOT NULL,
  price INTEGER,
  weight INTEGER,
  stock INTEGER NOT NULL DEFAULT 0,
  reserved INTEGER NOT NULL DEFAULT 0,
  deduction INTEGER NOT NULL DEFAULT 1,
  linked_name TEXT,
  linked_qty INTEGER,
  linked_weight INTEGER,
  gallery TEXT NOT NULL DEFAULT '[]',
  variants TEXT NOT NULL DEFAULT '[]',
  addons TEXT NOT NULL DEFAULT '[]',
  archived INTEGER NOT NULL DEFAULT 0,
  created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS orders (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  order_number TEXT NOT NULL UNIQUE,
  customer_name TEXT NOT NULL,
  amount INTEGER NOT NULL,
  payment_method TEXT NOT NULL DEFAULT 'card',
  payment_status_label TEXT NOT NULL DEFAULT 'Fizetve',
  payment_status_class TEXT NOT NULL DEFAULT 'pill pill--success',
  payment_sync_note TEXT,
  shipping_status_label TEXT,
  shipping_status_class TEXT,
  shipping_sync_note TEXT,
  invoiced INTEGER NOT NULL DEFAULT 0,
  invoice_file TEXT,
  invoice_stored_name TEXT,
  courier TEXT,
  order_date TEXT NOT NULL,
  order_time TEXT,
  address TEXT,
  stripe_ref TEXT,
  is_listed INTEGER NOT NULL DEFAULT 1,
  coupon_code TEXT,
  stock_released INTEGER NOT NULL DEFAULT 0,
  ship_address TEXT,
  ship_city TEXT,
  ship_postcode TEXT,
  ship_country TEXT,
  delivery_point_type TEXT,
  delivery_point_id TEXT,
  kvikk_shipment_status TEXT,
  kvikk_tracking_number TEXT,
  kvikk_tracking_link TEXT,
  kvikk_label_stored_name TEXT,
  kvikk_shipment_error TEXT,
  created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS order_items (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  order_id INTEGER NOT NULL REFERENCES orders(id) ON DELETE CASCADE,
  product_name TEXT NOT NULL,
  qty INTEGER NOT NULL,
  unit_price INTEGER NOT NULL
);

CREATE TABLE IF NOT EXISTS customers (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  customer_key TEXT NOT NULL UNIQUE,
  name TEXT NOT NULL,
  email TEXT,
  phone TEXT,
  created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS customer_purchases (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  customer_id INTEGER NOT NULL REFERENCES customers(id) ON DELETE CASCADE,
  purchase_date TEXT NOT NULL,
  order_number TEXT NOT NULL,
  product_label TEXT NOT NULL,
  amount TEXT NOT NULL,
  status TEXT NOT NULL,
  stripe_ref TEXT
);

-- customer_name/amount/stripe_ref nincs itt duplikálva — az orders táblából jön order_number alapján
CREATE TABLE IF NOT EXISTS returns (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  order_number TEXT NOT NULL UNIQUE REFERENCES orders(order_number),
  return_date TEXT NOT NULL,
  kvikk_status TEXT NOT NULL,
  stripe_status TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS coupons (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  code TEXT NOT NULL UNIQUE,
  discount_type TEXT NOT NULL DEFAULT 'percent',
  discount_value INTEGER NOT NULL,
  start_now INTEGER NOT NULL DEFAULT 1,
  from_date TEXT,
  until_date TEXT,
  usage_limit INTEGER,
  used_count INTEGER NOT NULL DEFAULT 0,
  limit_per_email INTEGER,
  status TEXT NOT NULL DEFAULT 'success',
  note TEXT,
  excluded_products TEXT NOT NULL DEFAULT '[]',
  created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- egyszerű kulcs-érték beállítások (pl. email feladó név/cím)
CREATE TABLE IF NOT EXISTS settings (
  key TEXT PRIMARY KEY,
  value TEXT
);

CREATE TABLE IF NOT EXISTS email_templates (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  name TEXT NOT NULL,
  trigger_label TEXT NOT NULL,
  subject TEXT NOT NULL,
  body TEXT NOT NULL,
  status TEXT NOT NULL DEFAULT 'success',
  created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS legal_docs (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  name TEXT NOT NULL,
  url TEXT,
  effective_date TEXT,
  status TEXT NOT NULL DEFAULT 'neutral',
  content TEXT,
  archived INTEGER NOT NULL DEFAULT 0,
  created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS admin_users (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  email TEXT NOT NULL UNIQUE,
  password_hash TEXT NOT NULL,
  created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  last_login_at TEXT
);

CREATE TABLE IF NOT EXISTS gdpr_requests (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  email TEXT NOT NULL,
  request_type TEXT NOT NULL,
  received_date TEXT NOT NULL,
  status TEXT NOT NULL DEFAULT 'warn',
  created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- bejelentkezési védelmek (rate limit, "emlékezz rám", jelszó-visszaállítás)
CREATE TABLE IF NOT EXISTS login_attempts (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  ip TEXT NOT NULL,
  attempted_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS remember_tokens (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  admin_id INTEGER NOT NULL REFERENCES admin_users(id) ON DELETE CASCADE,
  selector TEXT NOT NULL UNIQUE,
  validator_hash TEXT NOT NULL,
  expires_at TEXT NOT NULL,
  created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS password_resets (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  admin_id INTEGER NOT NULL REFERENCES admin_users(id) ON DELETE CASCADE,
  token_hash TEXT NOT NULL,
  expires_at TEXT NOT NULL,
  used INTEGER NOT NULL DEFAULT 0,
  created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);
