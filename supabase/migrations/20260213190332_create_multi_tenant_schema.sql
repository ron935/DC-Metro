-- ============================================
-- IPW Multi-Tenant Schema
-- ============================================

-- 1. Businesses table (one row per client)
CREATE TABLE businesses (
    id UUID DEFAULT gen_random_uuid() PRIMARY KEY,
    name TEXT NOT NULL,
    slug TEXT UNIQUE NOT NULL,
    website_url TEXT,
    active BOOLEAN DEFAULT true,
    created_at TIMESTAMPTZ DEFAULT now()
);

-- 2. Profiles table (linked to Supabase Auth users)
CREATE TABLE profiles (
    id UUID REFERENCES auth.users(id) ON DELETE CASCADE PRIMARY KEY,
    business_id UUID REFERENCES businesses(id) ON DELETE SET NULL,
    full_name TEXT,
    role TEXT NOT NULL DEFAULT 'client' CHECK (role IN ('admin', 'client')),
    created_at TIMESTAMPTZ DEFAULT now()
);

-- 3. Quotes table (form submissions from websites)
CREATE TABLE quotes (
    id UUID DEFAULT gen_random_uuid() PRIMARY KEY,
    business_id UUID REFERENCES businesses(id) ON DELETE CASCADE NOT NULL,
    name TEXT NOT NULL,
    email TEXT NOT NULL,
    phone TEXT,
    company TEXT,
    service TEXT,
    budget TEXT,
    timeline TEXT,
    message TEXT,
    status TEXT DEFAULT 'new' CHECK (status IN ('new', 'reviewed', 'contacted', 'closed')),
    created_at TIMESTAMPTZ DEFAULT now()
);

-- ============================================
-- Row Level Security Policies
-- ============================================

-- Enable RLS on all tables
ALTER TABLE businesses ENABLE ROW LEVEL SECURITY;
ALTER TABLE profiles ENABLE ROW LEVEL SECURITY;
ALTER TABLE quotes ENABLE ROW LEVEL SECURITY;

-- Profiles: users can read their own profile
CREATE POLICY "Users can view own profile"
    ON profiles FOR SELECT
    USING (auth.uid() = id);

-- Profiles: users can update their own profile
CREATE POLICY "Users can update own profile"
    ON profiles FOR UPDATE
    USING (auth.uid() = id);

-- Businesses: admins see all, clients see their own
CREATE POLICY "Admins can view all businesses"
    ON businesses FOR SELECT
    USING (
        EXISTS (
            SELECT 1 FROM profiles
            WHERE profiles.id = auth.uid()
            AND profiles.role = 'admin'
        )
    );

CREATE POLICY "Clients can view their own business"
    ON businesses FOR SELECT
    USING (
        EXISTS (
            SELECT 1 FROM profiles
            WHERE profiles.id = auth.uid()
            AND profiles.business_id = businesses.id
        )
    );

-- Businesses: only admins can insert/update/delete
CREATE POLICY "Admins can manage businesses"
    ON businesses FOR ALL
    USING (
        EXISTS (
            SELECT 1 FROM profiles
            WHERE profiles.id = auth.uid()
            AND profiles.role = 'admin'
        )
    );

-- Quotes: admins see all, clients see their business quotes
CREATE POLICY "Admins can view all quotes"
    ON quotes FOR SELECT
    USING (
        EXISTS (
            SELECT 1 FROM profiles
            WHERE profiles.id = auth.uid()
            AND profiles.role = 'admin'
        )
    );

CREATE POLICY "Clients can view their business quotes"
    ON quotes FOR SELECT
    USING (
        EXISTS (
            SELECT 1 FROM profiles
            WHERE profiles.id = auth.uid()
            AND profiles.business_id = quotes.business_id
        )
    );

-- Quotes: admins can manage all, clients can update status on their own
CREATE POLICY "Admins can manage all quotes"
    ON quotes FOR ALL
    USING (
        EXISTS (
            SELECT 1 FROM profiles
            WHERE profiles.id = auth.uid()
            AND profiles.role = 'admin'
        )
    );

CREATE POLICY "Clients can update their business quotes"
    ON quotes FOR UPDATE
    USING (
        EXISTS (
            SELECT 1 FROM profiles
            WHERE profiles.id = auth.uid()
            AND profiles.business_id = quotes.business_id
        )
    );

-- Quotes: allow anonymous inserts (from website forms via anon key)
CREATE POLICY "Anyone can submit a quote"
    ON quotes FOR INSERT
    WITH CHECK (true);
