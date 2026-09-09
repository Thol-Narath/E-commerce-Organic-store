import { useRef, useState } from 'react';
import {
  Alert, Badge, Button, Card, Col, Form, Row, Spinner,
} from 'react-bootstrap';
import { useAuth } from '../../context/AuthContext';
import { authService } from '../../services/authService';
import { normalizeError } from '../../services/api';
import usePageTitle from '../../hooks/usePageTitle';
import { formatDate } from '../../utils/format';
import { useToast } from '../../context/ToastContext';
import AccountLayout from '../../layouts/AccountLayout';
import Breadcrumbs from '../../components/common/Breadcrumbs';
import ImageWithFallback from '../../components/common/ImageWithFallback';
import {
  AlertIcon,
  CalendarIcon,
  CameraIcon,
  CheckCircleIcon,
  EyeIcon,
  EyeOffIcon,
  LockIcon,
  MailIcon,
  PhoneIcon,
  SaveIcon,
  ShieldIcon,
  UserCircleIcon,
} from '../../assets/icons';

export default function ProfilePage() {
  usePageTitle('My Profile');
  const { user, updateUser } = useAuth();
  const { showToast } = useToast();
  const fileInputRef = useRef(null);

  // Personal information state
  const [form, setForm] = useState({
    name: user?.name || '',
    email: user?.email || '',
    phone: user?.phone || '',
  });
  const [saving, setSaving] = useState(false);
  const [success, setSuccess] = useState('');
  const [errors, setErrors] = useState(null);
  const [serverError, setServerError] = useState('');

  // Password state
  const [pwForm, setPwForm] = useState({
    current_password: '',
    password: '',
    password_confirmation: '',
  });
  const [pwSaving, setPwSaving] = useState(false);
  const [pwSuccess, setPwSuccess] = useState('');
  const [pwErrors, setPwErrors] = useState(null);
  const [pwServerError, setPwServerError] = useState('');
  const [showPw, setShowPw] = useState(false);

  // Avatar state
  const [avatarBusy, setAvatarBusy] = useState(false);

  const handleChange = (e) => {
    setForm({ ...form, [e.target.name]: e.target.value });
  };

  const handleSubmit = async (e) => {
    e.preventDefault();
    setSaving(true);
    setSuccess('');
    setErrors(null);
    setServerError('');

    try {
      const result = await authService.updateProfile(form);
      updateUser(result.user);
      setSuccess('Your profile has been updated.');
      showToast('Profile updated successfully.');
    } catch (err) {
      const apiError = normalizeError(err);
      setServerError(apiError.message);
      setErrors(apiError.errors);
    } finally {
      setSaving(false);
    }
  };

  const handlePwChange = (e) => {
    setPwForm({ ...pwForm, [e.target.name]: e.target.value });
  };

  const handlePasswordSubmit = async (e) => {
    e.preventDefault();
    setPwSaving(true);
    setPwSuccess('');
    setPwErrors(null);
    setPwServerError('');

    try {
      await authService.changePassword(pwForm);
      setPwSuccess('Your password has been changed.');
      showToast('Password changed successfully.');
      setPwForm({ current_password: '', password: '', password_confirmation: '' });
    } catch (err) {
      const apiError = normalizeError(err);
      setPwServerError(apiError.message);
      setPwErrors(apiError.errors);
    } finally {
      setPwSaving(false);
    }
  };

  const handleAvatarClick = () => fileInputRef.current?.click();

  const handleAvatarChange = async (e) => {
    const file = e.target.files?.[0];
    if (!file) return;
    setAvatarBusy(true);
    try {
      const result = await authService.uploadAvatar(file);
      updateUser(result.user);
      showToast('Profile photo updated.');
    } catch (err) {
      showToast(normalizeError(err).message, 'danger');
    } finally {
      setAvatarBusy(false);
      e.target.value = '';
    }
  };

  const initial = (user?.name || '?').charAt(0).toUpperCase();
  const isActive = !user || user.status !== 'inactive';

  return (
    <AccountLayout>
      <Breadcrumbs items={[{ label: 'Home', to: '/' }, { label: 'My Account', to: '/account/profile' }, { label: 'Profile' }]} />

      <div className="account-page-header">
        <h1 className="h3 mb-1 d-flex align-items-center gap-2">
          <UserCircleIcon size={26} className="text-success" />
          My Profile
        </h1>
        <p className="text-muted mb-0">Manage your personal information, security preferences, and avatar.</p>
      </div>

      {(success || pwSuccess) && (
        <Alert variant="success" className="profile-alert-banner shadow-sm">
          <CheckCircleIcon size={20} className="text-success flex-shrink-0" />
          <span>{success || pwSuccess}</span>
        </Alert>
      )}
      {(serverError || pwServerError) && (
        <Alert variant="danger" className="profile-alert-banner shadow-sm">
          <AlertIcon size={20} className="text-danger flex-shrink-0" />
          <span>{serverError || pwServerError}</span>
        </Alert>
      )}

      <Row className="g-4">
        {/* Left: avatar & summary card */}
        <Col lg={4}>
          <Card className="account-card shadow-sm h-100">
            <Card.Body className="profile-card-avatar">
              <div className="profile-avatar-wrap">
                <div className="profile-avatar-lg">
                  {user?.avatar ? (
                    <ImageWithFallback src={user.avatar} alt={user?.name || 'Account'} />
                  ) : (
                    <span>{initial}</span>
                  )}
                </div>
                <button
                  type="button"
                  className="profile-avatar-camera"
                  onClick={handleAvatarClick}
                  aria-label="Upload profile photo"
                  title="Upload profile photo"
                  disabled={avatarBusy}
                >
                  {avatarBusy ? (
                    <Spinner animation="border" size="sm" />
                  ) : (
                    <CameraIcon size={16} />
                  )}
                </button>
                <input
                  ref={fileInputRef}
                  type="file"
                  accept="image/*"
                  className="d-none"
                  onChange={handleAvatarChange}
                  aria-hidden="true"
                />
              </div>

              <h2 className="h5 mt-3 mb-1">{user?.name}</h2>
              <p className="text-muted small mb-3">{user?.email}</p>

              <div className="profile-meta-list mt-2">
                <span>
                  <CalendarIcon size={17} className="text-success flex-shrink-0" />
                  Member since {formatDate(user?.created_at)}
                </span>
                <span>
                  <ShieldIcon size={17} className="text-success flex-shrink-0" />
                  Account Status:{' '}
                  <Badge bg={isActive ? 'success' : 'secondary'} pill>
                    {isActive ? 'Active' : 'Inactive'}
                  </Badge>
                </span>
              </div>
            </Card.Body>
          </Card>
        </Col>

        {/* Right: personal info & security */}
        <Col lg={8}>
          {/* Personal Information */}
          <Card className="account-card shadow-sm mb-4">
            <Card.Header className="bg-white">
              <h2 className="h6 mb-0">Personal Information</h2>
              <p className="small text-muted mb-0 mt-1">Update your details to keep your account current.</p>
            </Card.Header>
            <Card.Body>
              <Form onSubmit={handleSubmit} noValidate>
                <Form.Group className="mb-3" controlId="profileName">
                  <Form.Label>Full Name</Form.Label>
                  <div className="input-with-icon">
                    <UserCircleIcon size={17} />
                    <Form.Control
                      type="text"
                      name="name"
                      value={form.name}
                      onChange={handleChange}
                      isInvalid={Boolean(errors?.name)}
                      required
                    />
                    <Form.Control.Feedback type="invalid">{errors?.name?.[0]}</Form.Control.Feedback>
                  </div>
                </Form.Group>

                <Form.Group className="mb-3" controlId="profileEmail">
                  <Form.Label>Email Address</Form.Label>
                  <div className="input-with-icon">
                    <MailIcon size={17} />
                    <Form.Control
                      type="email"
                      name="email"
                      value={form.email}
                      onChange={handleChange}
                      isInvalid={Boolean(errors?.email)}
                      required
                    />
                    <Form.Control.Feedback type="invalid">{errors?.email?.[0]}</Form.Control.Feedback>
                  </div>
                </Form.Group>

                <Form.Group className="mb-4" controlId="profilePhone">
                  <Form.Label>Phone Number</Form.Label>
                  <div className="input-with-icon">
                    <PhoneIcon size={17} />
                    <Form.Control
                      type="tel"
                      name="phone"
                      value={form.phone}
                      onChange={handleChange}
                      isInvalid={Boolean(errors?.phone)}
                    />
                    <Form.Control.Feedback type="invalid">{errors?.phone?.[0]}</Form.Control.Feedback>
                  </div>
                </Form.Group>

                <div className="d-flex justify-content-end">
                  <Button type="submit" variant="success" disabled={saving} className="d-inline-flex align-items-center gap-2 px-4">
                    {saving ? (
                      <>
                        <Spinner animation="border" size="sm" />
                        Saving...
                      </>
                    ) : (
                      <>
                        <SaveIcon size={17} />
                        Save Changes
                      </>
                    )}
                  </Button>
                </div>
              </Form>
            </Card.Body>
          </Card>

          {/* Change Password */}
          <Card className="account-card shadow-sm">
            <Card.Header className="bg-white">
              <h2 className="h6 mb-0">Change Password</h2>
              <p className="small text-muted mb-0 mt-1">Ensure your account is secure by using a strong password.</p>
            </Card.Header>
            <Card.Body>
              <Form onSubmit={handlePasswordSubmit} noValidate>
                <Form.Group className="mb-3" controlId="currentPassword">
                  <Form.Label>Current Password</Form.Label>
                  <div className="position-relative">
                    <Form.Control
                      type={showPw ? 'text' : 'password'}
                      name="current_password"
                      value={pwForm.current_password}
                      onChange={handlePwChange}
                      isInvalid={Boolean(pwErrors?.current_password)}
                      required
                    />
                    <button
                      type="button"
                      className="password-toggle"
                      onClick={() => setShowPw((v) => !v)}
                      aria-label={showPw ? 'Hide passwords' : 'Show passwords'}
                    >
                      {showPw ? <EyeOffIcon size={18} /> : <EyeIcon size={18} />}
                    </button>
                  </div>
                  <Form.Control.Feedback type="invalid">{pwErrors?.current_password?.[0]}</Form.Control.Feedback>
                </Form.Group>

                <Form.Group className="mb-3" controlId="newPassword">
                  <Form.Label>New Password</Form.Label>
                  <div className="input-with-icon">
                    <LockIcon size={17} />
                    <Form.Control
                      type={showPw ? 'text' : 'password'}
                      name="password"
                      value={pwForm.password}
                      onChange={handlePwChange}
                      isInvalid={Boolean(pwErrors?.password)}
                      required
                    />
                    <Form.Control.Feedback type="invalid">{pwErrors?.password?.[0]}</Form.Control.Feedback>
                  </div>
                </Form.Group>

                <Form.Group className="mb-4" controlId="confirmPassword">
                  <Form.Label>Confirm New Password</Form.Label>
                  <div className="input-with-icon">
                    <LockIcon size={17} />
                    <Form.Control
                      type={showPw ? 'text' : 'password'}
                      name="password_confirmation"
                      value={pwForm.password_confirmation}
                      onChange={handlePwChange}
                      isInvalid={Boolean(pwErrors?.password_confirmation)}
                      required
                    />
                    <Form.Control.Feedback type="invalid">{pwErrors?.password_confirmation?.[0]}</Form.Control.Feedback>
                  </div>
                </Form.Group>

                <div className="d-flex justify-content-end">
                  <Button type="submit" variant="dark" disabled={pwSaving} className="d-inline-flex align-items-center gap-2 px-4">
                    {pwSaving ? (
                      <>
                        <Spinner animation="border" size="sm" />
                        Updating...
                      </>
                    ) : (
                      <>
                        <LockIcon size={17} />
                        Update Password
                      </>
                    )}
                  </Button>
                </div>
              </Form>
            </Card.Body>
          </Card>
        </Col>
      </Row>
    </AccountLayout>
  );
}