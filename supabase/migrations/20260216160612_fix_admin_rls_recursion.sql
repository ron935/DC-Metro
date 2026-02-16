-- Fix infinite recursion: the admin policies on profiles referenced profiles
-- itself, causing a recursive RLS loop. Use a SECURITY DEFINER function
-- that bypasses RLS to check the admin role instead.

-- 1. Create helper function (bypasses RLS)
CREATE OR REPLACE FUNCTION public.is_admin()
RETURNS boolean
LANGUAGE sql
SECURITY DEFINER
STABLE
SET search_path = ''
AS $$
    SELECT EXISTS (
        SELECT 1 FROM public.profiles
        WHERE id = auth.uid()
        AND role = 'admin'
    );
$$;

-- 2. Drop the broken self-referencing policies
DROP POLICY IF EXISTS "Admins can view all profiles" ON profiles;
DROP POLICY IF EXISTS "Admins can update all profiles" ON profiles;

-- 3. Re-create using the helper function (no recursion)
CREATE POLICY "Admins can view all profiles"
    ON profiles FOR SELECT
    USING (public.is_admin());

CREATE POLICY "Admins can update all profiles"
    ON profiles FOR UPDATE
    USING (public.is_admin());
