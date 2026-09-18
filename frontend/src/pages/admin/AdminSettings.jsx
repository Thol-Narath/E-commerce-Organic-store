import { useEffect, useMemo, useRef, useState } from 'react';
import { Alert, Button, Card, Col, Form, Row, Spinner } from 'react-bootstrap';
import { adminSettingsService } from '../../services/adminSettingsService';
import { settingsService } from '../../services/settingsService';
import { normalizeError } from '../../services/api';
import { buildMapSrc, mapSourceHint } from '../../utils/maps';
import { ImageIcon, MapPinIcon, SaveIcon, StoreIcon } from '../../assets/icons';

const DEFAULT_LOGO_HEIGHT = 42;

export default function AdminSettings() {
  const [logo, setLogo] = useState(null);
  const [logoPreview, setLogoPreview] = useState(null);
  const [logoFile, setLogoFile] = useState(null);

  const [storeName, setStoreName] = useState('');
  const [tagline, setTagline] = useState('');
  const [logoHeight, setLogoHeight] = useState(DEFAULT_LOGO_HEIGHT);

  const [contactAddress, setContactAddress] = useState('');
  const [contactPhone, setContactPhone] = useState('');
  const [contactEmail, setContactEmail] = useState('');

  const [aboutImage, setAboutImage] = useState(null);
  const [aboutImagePreview, setAboutImagePreview] = useState(null);
  const [aboutImageFile, setAboutImageFile] = useState(null);

  const [locStoreName, setLocStoreName] = useState('');
  const [locAddress, setLocAddress] = useState('');
  const [locPhone, setLocPhone] = useState('');
  const [locEmail, setLocEmail] = useState('');
  const [locLatitude, setLocLatitude] = useState('');
  const [locLongitude, setLocLongitude] = useState('');
  const [locMapsUrl, setLocMapsUrl] = useState('');
  const [locEmbedUrl, setLocEmbedUrl] = useState('');
  const [locBusinessHours, setLocBusinessHours] = useState('');

  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [savingBrand, setSavingBrand] = useState(false);
  const [savingContact, setSavingContact] = useState(false);
  const [savingAbout, setSavingAbout] = useState(false);
  const [savingLocation, setSavingLocation] = useState(false);
  const [error, setError] = useState('');
  const [success, setSuccess] = useState('');
  const fileInputRef = useRef(null);
  const aboutFileInputRef = useRef(null);

  const locMapSrc = useMemo(
    () => buildMapSrc({ latitude: locLatitude, longitude: locLongitude, embedUrl: locEmbedUrl }),
    [locLatitude, locLongitude, locEmbedUrl],
  );

  const locMapHint = mapSourceHint({
    latitude: locLatitude,
    longitude: locLongitude,
    embedUrl: locEmbedUrl,
  });

  useEffect(() => {
    let cancelled = false;
    Promise.all([
      adminSettingsService.getLogo(),
      adminSettingsService.getAboutImage(),
      adminSettingsService.getStoreLocation(),
      settingsService.publicSettings(),
    ])
      .then(([logoData, aboutData, locationData, publicData]) => {
        if (cancelled) return;
        const store = publicData?.store || {};
        const contact = publicData?.contact || {};
        setLogo(logoData.logo || null);
        setLogoPreview(logoData.logo || null);
        setAboutImage(aboutData.image_url || null);
        setAboutImagePreview(aboutData.image_url || null);
        setStoreName(store.name || '');
        setTagline(store.tagline || '');
        setLogoHeight(store.logo_height || DEFAULT_LOGO_HEIGHT);
        setContactAddress(contact.address || '');
        setContactPhone(contact.phone || '');
        setContactEmail(contact.email || '');
        setLocStoreName(locationData?.store_name || store.name || '');
        setLocAddress(locationData?.address ?? contact.address ?? '');
        setLocPhone(locationData?.phone ?? contact.phone ?? '');
        setLocEmail(locationData?.email ?? contact.email ?? '');
        setLocLatitude(locationData?.latitude ?? '');
        setLocLongitude(locationData?.longitude ?? '');
        setLocMapsUrl(locationData?.google_maps_url ?? '');
        setLocEmbedUrl(locationData?.google_maps_embed_url ?? '');
        setLocBusinessHours(locationData?.business_hours ?? '');
      })
      .catch((e) => {
        if (!cancelled) setError(normalizeError(e).message);
      })
      .finally(() => {
        if (!cancelled) setLoading(false);
      });
    return () => {
      cancelled = true;
    };
  }, []);

  const handleFileChange = (e) => {
    const file = e.target.files?.[0] || null;
    setLogoFile(file);
    if (file) {
      setLogoPreview(URL.createObjectURL(file));
    } else {
      setLogoPreview(logo);
    }
  };

  const handleUpload = async (e) => {
    e.preventDefault();
    if (!logoFile) return;
    setSaving(true);
    setError('');
    setSuccess('');
    try {
      const data = await adminSettingsService.uploadLogo(logoFile);
      setLogo(data.logo);
      setLogoPreview(data.logo);
      setLogoFile(null);
      if (fileInputRef.current) fileInputRef.current.value = '';
      setSuccess('Store logo updated. Refresh the storefront header to see it live.');
    } catch (err) {
      setError(normalizeError(err).message);
    } finally {
      setSaving(false);
    }
  };

  const handleRemove = async () => {
    if (!window.confirm('Remove the current logo?')) return;
    setSaving(true);
    setError('');
    setSuccess('');
    try {
      await adminSettingsService.removeLogo();
      setLogo(null);
      setLogoPreview(null);
      setLogoFile(null);
      if (fileInputRef.current) fileInputRef.current.value = '';
      setSuccess('Logo removed. The storefront will fall back to the default logo.');
    } catch (err) {
      setError(normalizeError(err).message);
    } finally {
      setSaving(false);
    }
  };

  const handleAboutFileChange = (e) => {
    const file = e.target.files?.[0] || null;
    setAboutImageFile(file);
    if (file) {
      setAboutImagePreview(URL.createObjectURL(file));
    } else {
      setAboutImagePreview(aboutImage);
    }
  };

  const handleAboutUpload = async (e) => {
    e.preventDefault();
    if (!aboutImageFile) return;
    setSavingAbout(true);
    setError('');
    setSuccess('');
    try {
      const data = await adminSettingsService.uploadAboutImage(aboutImageFile);
      setAboutImage(data.image_url);
      setAboutImagePreview(data.image_url);
      setAboutImageFile(null);
      if (aboutFileInputRef.current) aboutFileInputRef.current.value = '';
      setSuccess('About image updated. The homepage About section now shows this image.');
    } catch (err) {
      setError(normalizeError(err).message);
    } finally {
      setSavingAbout(false);
    }
  };

  const handleAboutRemove = async () => {
    if (!window.confirm('Remove the About section image? The homepage will fall back to the default slides.')) return;
    setSavingAbout(true);
    setError('');
    setSuccess('');
    try {
      await adminSettingsService.removeAboutImage();
      setAboutImage(null);
      setAboutImagePreview(null);
      setAboutImageFile(null);
      if (aboutFileInputRef.current) aboutFileInputRef.current.value = '';
      setSuccess('About image removed. The homepage will fall back to the default slides.');
    } catch (err) {
      setError(normalizeError(err).message);
    } finally {
      setSavingAbout(false);
    }
  };

  const handleSaveLocation = async (e) => {
    e.preventDefault();
    const lat = locLatitude.trim();
    const lng = locLongitude.trim();
    const latNum = lat ? Number(lat) : null;
    const lngNum = lng ? Number(lng) : null;
    if (lat && (Number.isNaN(latNum) || latNum < -90 || latNum > 90)) {
      setError('Latitude must be a number between -90 and 90.');
      return;
    }
    if (lng && (Number.isNaN(lngNum) || lngNum < -180 || lngNum > 180)) {
      setError('Longitude must be a number between -180 and 180.');
      return;
    }
    setSavingLocation(true);
    setError('');
    setSuccess('');
    try {
      const data = await adminSettingsService.updateStoreLocation({
        store_name: locStoreName,
        address: locAddress,
        phone: locPhone,
        email: locEmail,
        latitude: lat,
        longitude: lng,
        google_maps_url: locMapsUrl,
        google_maps_embed_url: locEmbedUrl,
        business_hours: locBusinessHours,
      });
      setLocStoreName(data.store_name || '');
      setLocAddress(data.address || '');
      setLocPhone(data.phone || '');
      setLocEmail(data.email || '');
      setLocLatitude(data.latitude ?? '');
      setLocLongitude(data.longitude ?? '');
      setLocMapsUrl(data.google_maps_url || '');
      setLocEmbedUrl(data.google_maps_embed_url || '');
      setLocBusinessHours(data.business_hours || '');
      setSuccess('Store location saved. The About page updates immediately on refresh.');
    } catch (err) {
      setError(normalizeError(err).message);
    } finally {
      setSavingLocation(false);
    }
  };

  const handleSaveBranding = async (e) => {
    e.preventDefault();
    setSavingBrand(true);
    setError('');
    setSuccess('');
    try {
      const data = await adminSettingsService.updateStoreBranding({
        name: storeName,
        tagline,
        logo_height: Number(logoHeight),
      });
      setStoreName(data.name);
      setTagline(data.tagline || '');
      setLogoHeight(data.logo_height);
      setSuccess('Store branding saved. The storefront header and footer update immediately on refresh.');
    } catch (err) {
      setError(normalizeError(err).message);
    } finally {
      setSavingBrand(false);
    }
  };

  const handleSaveContact = async (e) => {
    e.preventDefault();
    setSavingContact(true);
    setError('');
    setSuccess('');
    try {
      const data = await adminSettingsService.updateContact({
        address: contactAddress,
        phone: contactPhone,
        email: contactEmail,
      });
      setContactAddress(data.address || '');
      setContactPhone(data.phone || '');
      setContactEmail(data.email || '');
      setSuccess('Contact information saved. The storefront footer updates immediately on refresh.');
    } catch (err) {
      setError(normalizeError(err).message);
    } finally {
      setSavingContact(false);
    }
  };

  return (
    <div>
      <div className="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
        <h2 className="h4 mb-0">Settings</h2>
      </div>

      {error && <Alert variant="danger">{error}</Alert>}
      {success && <Alert variant="success">{success}</Alert>}

      {loading ? (
        <div className="text-center py-5">
          <Spinner animation="border" variant="success" />
        </div>
      ) : (
        <>
          <Row className="g-4">
          {/* Store identity + logo size */}
          <Col lg={7} xl={8}>
            <Card className="shadow-sm">
              <Card.Body>
                <h5 className="mb-1 d-flex align-items-center gap-2">
                  <StoreIcon size={20} className="text-success" />
                  Store Branding
                </h5>
                <p className="text-muted small mb-3">
                  Edit how the store name, tagline and logo size appear across the storefront.
                </p>

                <Form onSubmit={handleSaveBranding}>
                  <Form.Group className="mb-3">
                    <Form.Label>Store name</Form.Label>
                    <Form.Control
                      type="text"
                      value={storeName}
                      onChange={(e) => setStoreName(e.target.value)}
                      maxLength={100}
                      required
                    />
                    <Form.Control.Feedback type="invalid">A store name is required.</Form.Control.Feedback>
                  </Form.Group>

                  <Form.Group className="mb-3">
                    <Form.Label>Tagline (optional)</Form.Label>
                    <Form.Control
                      type="text"
                      value={tagline}
                      onChange={(e) => setTagline(e.target.value)}
                      maxLength={255}
                    />
                  </Form.Group>

                  <Form.Group className="mb-3">
                    <Form.Label>
                      Logo size <span className="text-muted fw-normal">({logoHeight}px tall)</span>
                    </Form.Label>
                    <Form.Range
                      min={16}
                      max={120}
                      step={2}
                      value={Number(logoHeight)}
                      onChange={(e) => setLogoHeight(Number(e.target.value))}
                    />
                    <div className="d-flex justify-content-between small text-muted">
                      <span>16px</span>
                      <span>120px</span>
                    </div>
                  </Form.Group>

                  <Button type="submit" variant="success" disabled={savingBrand}>
                    {savingBrand ? (
                      <>
                        <Spinner as="span" animation="border" size="sm" className="me-1" /> Saving...
                      </>
                    ) : (
                      <>
                        <SaveIcon size={16} className="me-1" /> Save Branding
                      </>
                    )}
                  </Button>
                </Form>
              </Card.Body>
            </Card>
          </Col>

          {/* Logo upload */}
          <Col lg={5} xl={4}>
            <Form onSubmit={handleUpload}>
              <Card className="shadow-sm">
                <Card.Body>
                  <h5 className="mb-1 d-flex align-items-center gap-2">
                    <ImageIcon size={20} className="text-success" />
                    Store Logo
                  </h5>
                  <p className="text-muted small mb-3">
                    Upload the logo shown in the storefront header. PNG, JPG, WebP or SVG up to 2MB.
                  </p>

                  <div
                    className="border rounded d-flex align-items-center justify-content-center mb-2"
                    style={{
                      height: 140,
                      backgroundColor: '#f8f9fa',
                      cursor: 'pointer',
                      overflow: 'hidden',
                    }}
                    onClick={() => fileInputRef.current?.click()}
                  >
                    {logoPreview ? (
                      <img
                        src={logoPreview}
                        alt="Logo preview"
                        style={{ maxWidth: '100%', maxHeight: '100%', objectFit: 'contain', padding: '0.75rem' }}
                      />
                    ) : (
                      <div className="text-center text-muted">
                        <ImageIcon size={32} className="mb-1" />
                        <div className="small fw-semibold">Click to upload</div>
                        <div className="small opacity-75">or drag and drop</div>
                      </div>
                    )}
                  </div>

                  <Form.Control
                    ref={fileInputRef}
                    type="file"
                    accept="image/png,image/jpeg,image/webp,image/svg+xml"
                    onChange={handleFileChange}
                    style={{ display: 'none' }}
                  />

                  <div className="d-flex flex-wrap gap-2 mt-2">
                    {logoFile && (
                      <Button type="submit" variant="success" disabled={saving}>
                        {saving ? 'Uploading...' : 'Save Logo'}
                      </Button>
                    )}
                    {logo && (
                      <Button variant="outline-danger" onClick={handleRemove} disabled={saving}>
                        Remove Logo
                      </Button>
                    )}
                    <Button variant="outline-secondary" onClick={() => fileInputRef.current?.click()} disabled={saving}>
                      Choose File
                    </Button>
                  </div>
                </Card.Body>
              </Card>
            </Form>
          </Col>
        </Row>

        <Row className="mt-4">
          <Col>
            <Card className="shadow-sm">
              <Card.Body>
                <h5 className="mb-1 d-flex align-items-center gap-2">
                  <StoreIcon size={20} className="text-success" />
                  Contact Information
                </h5>
                <p className="text-muted small mb-3">
                  Address, phone and email shown in the storefront footer.
                </p>

                <Form onSubmit={handleSaveContact}>
                  <Row className="g-3">
                    <Col md={6}>
                      <Form.Group>
                        <Form.Label>Address (optional)</Form.Label>
                        <Form.Control
                          type="text"
                          value={contactAddress}
                          onChange={(e) => setContactAddress(e.target.value)}
                          maxLength={255}
                          placeholder="123 Organic Lane, Greenville"
                        />
                      </Form.Group>
                    </Col>
                    <Col md={3}>
                      <Form.Group>
                        <Form.Label>Phone (optional)</Form.Label>
                        <Form.Control
                          type="text"
                          value={contactPhone}
                          onChange={(e) => setContactPhone(e.target.value)}
                          maxLength={60}
                          placeholder="+1 555 0100"
                        />
                      </Form.Group>
                    </Col>
                    <Col md={3}>
                      <Form.Group>
                        <Form.Label>Email (optional)</Form.Label>
                        <Form.Control
                          type="email"
                          value={contactEmail}
                          onChange={(e) => setContactEmail(e.target.value)}
                          maxLength={120}
                          placeholder="hello@store.com"
                        />
                      </Form.Group>
                    </Col>
                  </Row>

                  <Button type="submit" variant="success" className="mt-3" disabled={savingContact}>
                    {savingContact ? (
                      <>
                        <Spinner as="span" animation="border" size="sm" className="me-1" /> Saving...
                      </>
                    ) : (
                      <>
                        <SaveIcon size={16} className="me-1" /> Save Contact Info
                      </>
                    )}
                  </Button>
                </Form>
              </Card.Body>
            </Card>
          </Col>
        </Row>

        {/* About section image */}
        <Row className="mt-4">
          <Col lg={7}>
            <Form onSubmit={handleAboutUpload}>
              <Card className="shadow-sm">
                <Card.Body>
                  <h5 className="mb-1 d-flex align-items-center gap-2">
                    <ImageIcon size={20} className="text-success" />
                    About Section Image
                  </h5>
                  <p className="text-muted small mb-3">
                    Image shown in the "About Us" frame on the homepage. PNG, JPG or WebP up to 4MB.
                  </p>

                  <div
                    className="border rounded d-flex align-items-center justify-content-center mb-2"
                    style={{
                      aspectRatio: '4 / 3',
                      backgroundColor: '#f8f9fa',
                      cursor: 'pointer',
                      overflow: 'hidden',
                    }}
                    onClick={() => aboutFileInputRef.current?.click()}
                  >
                    {aboutImagePreview ? (
                      <img
                        src={aboutImagePreview}
                        alt="About section preview"
                        style={{ width: '100%', height: '100%', objectFit: 'cover' }}
                      />
                    ) : (
                      <div className="text-center text-muted">
                        <ImageIcon size={32} className="mb-1" />
                        <div className="small fw-semibold">Click to upload</div>
                        <div className="small opacity-75">or drag and drop</div>
                      </div>
                    )}
                  </div>

                  <Form.Control
                    ref={aboutFileInputRef}
                    type="file"
                    accept="image/png,image/jpeg,image/webp"
                    onChange={handleAboutFileChange}
                    style={{ display: 'none' }}
                  />

                  <div className="d-flex flex-wrap gap-2 mt-2">
                    {aboutImageFile && (
                      <Button type="submit" variant="success" disabled={savingAbout}>
                        {savingAbout ? 'Uploading...' : 'Save About Image'}
                      </Button>
                    )}
                    {aboutImage && (
                      <Button variant="outline-danger" onClick={handleAboutRemove} disabled={savingAbout}>
                        Remove Image
                      </Button>
                    )}
                    <Button variant="outline-secondary" onClick={() => aboutFileInputRef.current?.click()} disabled={savingAbout}>
                      Choose File
                    </Button>
                  </div>
                </Card.Body>
              </Card>
            </Form>
          </Col>
        </Row>

        {/* Store location + Google Map */}
        <Row className="mt-4">
          <Col>
            <Card className="shadow-sm">
              <Card.Body>
                <h5 className="mb-1 d-flex align-items-center gap-2">
                  <MapPinIcon size={20} className="text-success" />
                  Store Location &amp; Google Map
                </h5>
                <p className="text-muted small mb-3">
                  Store name, contact details and the map shown in the "Visit Our Store"
                  section of the About page.
                </p>

                <Form onSubmit={handleSaveLocation}>
                  <Row className="g-3">
                    <Col md={6}>
                      <Form.Group>
                        <Form.Label>Store name</Form.Label>
                        <Form.Control
                          type="text"
                          value={locStoreName}
                          onChange={(e) => setLocStoreName(e.target.value)}
                          maxLength={255}
                          required
                        />
                      </Form.Group>
                    </Col>
                    <Col md={6}>
                      <Form.Group>
                        <Form.Label>Business hours (optional)</Form.Label>
                        <Form.Control
                          type="text"
                          value={locBusinessHours}
                          onChange={(e) => setLocBusinessHours(e.target.value)}
                          maxLength={500}
                          placeholder="Monday – Sunday, 8:00 AM – 8:00 PM"
                        />
                      </Form.Group>
                    </Col>
                    <Col md={6}>
                      <Form.Group>
                        <Form.Label>Address (optional)</Form.Label>
                        <Form.Control
                          type="text"
                          value={locAddress}
                          onChange={(e) => setLocAddress(e.target.value)}
                          maxLength={500}
                          placeholder="No. 86A, Street 110, Phnom Penh"
                        />
                      </Form.Group>
                    </Col>
                    <Col md={6}>
                      <Form.Group>
                        <Form.Label>Phone (optional)</Form.Label>
                        <Form.Control
                          type="text"
                          value={locPhone}
                          onChange={(e) => setLocPhone(e.target.value)}
                          maxLength={50}
                          placeholder="+855 12 345 678"
                        />
                      </Form.Group>
                    </Col>
                    <Col md={6}>
                      <Form.Group>
                        <Form.Label>Email (optional)</Form.Label>
                        <Form.Control
                          type="email"
                          value={locEmail}
                          onChange={(e) => setLocEmail(e.target.value)}
                          maxLength={255}
                          placeholder="hello@delicacyorganic.com"
                        />
                      </Form.Group>
                    </Col>
                    <Col md={6}>
                      <Form.Group>
                        <Form.Label>Google Maps URL (optional)</Form.Label>
                        <Form.Control
                          type="url"
                          value={locMapsUrl}
                          onChange={(e) => setLocMapsUrl(e.target.value)}
                          maxLength={500}
                          placeholder="https://maps.google.com/?q=... or https://goo.gl/maps/..."
                        />
                      </Form.Group>
                    </Col>
                    <Col md={6}>
                      <Form.Group>
                        <Form.Label>Google Maps Embed URL (optional)</Form.Label>
                        <Form.Control
                          type="text"
                          value={locEmbedUrl}
                          onChange={(e) => setLocEmbedUrl(e.target.value)}
                          maxLength={500}
                          placeholder="https://maps.google.com/maps?q=...&output=embed"
                        />
                        <Form.Text className="text-muted">
                          Only Google "Embed a map" links (URLs ending in /maps/embed or with output=embed)
                          can be embedded. A regular Maps link or share link cannot be framed by the page and
                          will fall back to the coordinates-based map.
                        </Form.Text>
                      </Form.Group>
                    </Col>
                    <Col md={3}>
                      <Form.Group>
                        <Form.Label>Latitude (optional)</Form.Label>
                        <Form.Control
                          type="number"
                          step="any"
                          min={-90}
                          max={90}
                          value={locLatitude}
                          onChange={(e) => setLocLatitude(e.target.value)}
                          placeholder="11.5564"
                          isInvalid={locLatitude.trim() !== '' && (Number(locLatitude) < -90 || Number(locLatitude) > 90)}
                        />
                        <Form.Control.Feedback type="invalid">
                          Must be between -90 and 90.
                        </Form.Control.Feedback>
                      </Form.Group>
                    </Col>
                    <Col md={3}>
                      <Form.Group>
                        <Form.Label>Longitude (optional)</Form.Label>
                        <Form.Control
                          type="number"
                          step="any"
                          min={-180}
                          max={180}
                          value={locLongitude}
                          onChange={(e) => setLocLongitude(e.target.value)}
                          placeholder="104.9282"
                          isInvalid={locLongitude.trim() !== '' && (Number(locLongitude) < -180 || Number(locLongitude) > 180)}
                        />
                        <Form.Control.Feedback type="invalid">
                          Must be between -180 and 180.
                        </Form.Control.Feedback>
                      </Form.Group>
                    </Col>
                  </Row>

                  <div className="mt-3">
                    <Button type="submit" variant="success" disabled={savingLocation}>
                      {savingLocation ? (
                        <>
                          <Spinner as="span" animation="border" size="sm" className="me-1" /> Saving...
                        </>
                      ) : (
                        <>
                          <SaveIcon size={16} className="me-1" /> Save Store Location
                        </>
                      )}
                    </Button>
                  </div>

                  <div className="mt-4">
                    <div className="small fw-semibold text-muted mb-2">Map preview</div>
                    {locMapSrc ? (
                      <div className="admin-map-preview">
                        <iframe
                          src={locMapSrc}
                          title="Store location map preview"
                          style={{ border: 0, width: '100%', height: '100%' }}
                          loading="lazy"
                          referrerPolicy="no-referrer-when-downgrade"
                          allowFullScreen
                        />
                      </div>
                    ) : (
                      <div className="admin-map-preview admin-map-placeholder">
                        <MapPinIcon size={28} className="text-success mb-2" />
                        <p className="small mb-0">
                          Enter latitude/longitude or an embed URL to preview the map.
                        </p>
                      </div>
                    )}
                      <p className="small text-muted mt-2 mb-0">{locMapHint}</p>
                  </div>
                </Form>
              </Card.Body>
            </Card>
          </Col>
        </Row>
        </>
      )}
    </div>
  );
}