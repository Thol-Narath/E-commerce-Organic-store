import { useEffect, useRef, useState } from 'react';
import { Alert, Button, Card, Form, Spinner } from 'react-bootstrap';
import { adminSettingsService } from '../../services/adminSettingsService';
import { normalizeError } from '../../services/api';
import { ImageIcon } from '../../assets/icons';

export default function AdminSettings() {
  const [logo, setLogo] = useState(null);
  const [logoPreview, setLogoPreview] = useState(null);
  const [logoFile, setLogoFile] = useState(null);
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState('');
  const [success, setSuccess] = useState('');
  const fileInputRef = useRef(null);

  useEffect(() => {
    let cancelled = false;
    adminSettingsService
      .getLogo()
      .then((data) => {
        if (cancelled) return;
        setLogo(data.logo || null);
        setLogoPreview(data.logo || null);
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
      setSuccess('Logo updated successfully. Refresh the storefront header to see it live.');
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
        <Form onSubmit={handleUpload}>
          <Card className="shadow-sm" style={{ maxWidth: 520 }}>
            <Card.Body>
              <h5 className="mb-3">Store Logo</h5>
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
      )}
    </div>
  );
}