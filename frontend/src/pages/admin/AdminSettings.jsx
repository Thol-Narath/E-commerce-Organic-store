import { useEffect, useRef, useState } from 'react';
import { Alert, Button, Card, Col, Form, Row, Spinner } from 'react-bootstrap';
import { adminSettingsService } from '../../services/adminSettingsService';
import { settingsService } from '../../services/settingsService';
import { normalizeError } from '../../services/api';
import { ImageIcon, SaveIcon, StoreIcon } from '../../assets/icons';

const DEFAULT_LOGO_HEIGHT = 42;

export default function AdminSettings() {
  const [logo, setLogo] = useState(null);
  const [logoPreview, setLogoPreview] = useState(null);
  const [logoFile, setLogoFile] = useState(null);

  const [storeName, setStoreName] = useState('');
  const [tagline, setTagline] = useState('');
  const [logoHeight, setLogoHeight] = useState(DEFAULT_LOGO_HEIGHT);

  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [savingBrand, setSavingBrand] = useState(false);
  const [error, setError] = useState('');
  const [success, setSuccess] = useState('');
  const fileInputRef = useRef(null);

  useEffect(() => {
    let cancelled = false;
    Promise.all([adminSettingsService.getLogo(), settingsService.publicSettings()])
      .then(([logoData, publicData]) => {
        if (cancelled) return;
        const store = publicData?.store || {};
        setLogo(logoData.logo || null);
        setLogoPreview(logoData.logo || null);
        setStoreName(store.name || '');
        setTagline(store.tagline || '');
        setLogoHeight(store.logo_height || DEFAULT_LOGO_HEIGHT);
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
      )}
    </div>
  );
}