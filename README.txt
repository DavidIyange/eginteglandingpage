EG INTEGRATED SERVICES LTD - CYBERSECURITY LANDING PAGE
=========================================================

FILES
  index.html            The landing page (Netwrix Auditor + Microsoft Sentinel)
  thank-you.html        Shown after the form is sent (this is where the Google Ads conversion fires)
  privacy.html          Privacy notice TEMPLATE - have it reviewed and fill in the [brackets]
  submit.php            Receives the form, emails info@eginteg.com and saves a CSV copy
  assets/css/style.css  All styling (brand colours are at the top of the file)
  assets/js/main.js     Form checks, campaign tracking, cookie banner, Tag Manager loading
  leads/                Saved enquiries (leads.csv) - blocked from web access by .htaccess
  .htaccess             Security headers and caching

STEP 1 - CREATE THE SUBDOMAIN (cPanel)
  1. Log in to your hosting cPanel > Domains (or "Subdomains").
  2. Create a subdomain such as  lp  (it becomes lp.eginteg.com).
     Note the "document root" folder it creates (for example public_html/lp).
  3. Check that HTTPS works on it (cPanel > SSL/TLS Status > run AutoSSL). Wait until the padlock shows.
  If you do not manage the hosting yourself, send this step to whoever does.

STEP 2 - UPLOAD
  Upload EVERYTHING in this folder into the subdomain's document root (File Manager or FTP),
  keeping the folder structure. Make sure index.html sits directly in that root.

STEP 3 - EDIT THE SETTINGS
  index.html   : the <link rel="canonical"> address (use your real subdomain);
                 the WhatsApp links (https://wa.me/2347032222399) - confirm that number uses WhatsApp.
  submit.php   : TO_EMAIL (who receives enquiries) and FROM_EMAIL (must be an address on your domain,
                 for example no-reply@eginteg.com - create it in cPanel > Email Accounts if needed).
  assets/js/main.js : GTM_ID (your Google Tag Manager container ID, e.g. GTM-AB12CD3).
  Logo         : replace the "EG" square in index.html / thank-you.html (see the TODO comment in the header).
  Colours      : change the values at the top of assets/css/style.css.
  Content      : confirm every claim on the page is accurate for EG (support terms, partner status, etc.).
  privacy.html : fill in the [brackets] and have it reviewed.

STEP 4 - PROTECT SAVED ENQUIRIES (recommended)
  The leads folder is blocked by .htaccess, but the safest place is OUTSIDE the public web folder.
  If you can, create a folder above public_html (for example /home/youraccount/eg_leads), then change this line
  in submit.php to its full path:
      const LEADS_DIR = '/home/youraccount/eg_leads';
  If your server is NGINX (not Apache), .htaccess is ignored, so do this step.

STEP 5 - TEST BEFORE ADVERTISING
  [ ] Open the page on your phone and on a computer; the padlock (HTTPS) shows.
  [ ] Submit the form with a test enquiry: you are taken to thank-you.html.
  [ ] The email arrives at TO_EMAIL (check Spam too). If not, ask your host about PHP mail(),
      or switch to SMTP.
  [ ] A line appears in leads/leads.csv.
  [ ] Test with a campaign link, e.g. https://lp.eginteg.com/?utm_source=google&utm_medium=cpc&utm_campaign=cyber
      and confirm the campaign name appears in the email.
  [ ] Click Accept on the cookie banner, then use Tag Manager Preview mode to see tags fire.
  [ ] The call and WhatsApp buttons work on a phone.

STEP 6 - GOOGLE TAG MANAGER AND GOOGLE ADS TRACKING
  The page sends these events to Tag Manager's dataLayer:
      lead_form_submit   (on thank-you.html - this is your MAIN conversion)
      click_call         (any phone link)
      click_whatsapp     (any WhatsApp link)
      click_email        (email link in the footer / thank-you page)
  In Tag Manager:
    1. Create Custom Event triggers with those exact event names.
    2. Add a "Google Ads Conversion Tracking" tag for lead_form_submit (primary conversion),
       and tags for the call / WhatsApp events (secondary conversions).
    3. Add a Google Analytics 4 configuration tag on all pages, plus GA4 event tags for the same triggers.
    4. Set your Google Ads final URL to  https://lp.eginteg.com/  (add ?utm_source=google&utm_medium=cpc&utm_campaign={campaignid}
       or use the tracking template in Google Ads so campaign names reach your leads).
  Cookie banner: tags load only after a visitor clicks Accept. Visitors who decline are not measured.

NOTES
  - submit.php needs PHP 7.4 or newer (almost all shared hosting).
  - Form protection: hidden spam-trap field, minimum fill time, and 5 submissions per hour per IP.
  - Fonts load from Google Fonts. If they are blocked, the page falls back to Georgia / system fonts.
  - The audit-trail table on the page uses made-up sample data and says so in its caption.
