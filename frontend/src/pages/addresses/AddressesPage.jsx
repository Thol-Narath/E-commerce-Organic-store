import { useCallback, useEffect, useState } from 'react';
import { Alert, Button, Col, Container, Row, Spinner } from 'react-bootstrap';
import AddressCard from '../../components/address/AddressCard';
import AddressForm from '../../components/address/AddressForm';
import ConfirmDialog from '../../components/common/ConfirmDialog';
import EmptyState from '../../components/common/EmptyState';
import LoadingSpinner from '../../components/common/LoadingSpinner';
import PageHeader from '../../components/common/PageHeader';
import Breadcrumbs from '../../components/common/Breadcrumbs';
import AccountLayout from '../../layouts/AccountLayout';
import { addressService } from '../../services/addressService';
import { useToast } from '../../context/ToastContext';
import usePageTitle from '../../hooks/usePageTitle';
import { getErrorMessage } from '../../utils/error';
import { MapPinIcon, PlusIcon } from '../../assets/icons';

export default function AddressesPage() {
  usePageTitle('My Addresses');
  const { showToast } = useToast();

  const [addresses, setAddresses] = useState([]);
  const [loading, setLoading] = useState(true);
  const [loadError, setLoadError] = useState('');
  const [showForm, setShowForm] = useState(false);
  const [editing, setEditing] = useState(null);
  const [deleting, setDeleting] = useState(null);
  const [deletingBusy, setDeletingBusy] = useState(false);
  const [busyId, setBusyId] = useState(null);

  const load = useCallback(async () => {
    setLoading(true);
    setLoadError('');
    try {
      setAddresses(await addressService.list());
    } catch (err) {
      setLoadError(getErrorMessage(err));
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    load();
  }, [load]);

  const withBusy = async (id, fn) => {
    setBusyId(id);
    try {
      await fn();
    } finally {
      setBusyId(null);
    }
  };

  const handleCreate = async (payload) => {
    const created = await addressService.create(payload);
    await load();
    showToast('Address added successfully.');
    return created;
  };

  const handleEdit = (address) => {
    setEditing(address);
    setShowForm(true);
  };

  const handleUpdate = async (payload) => {
    await addressService.update(editing.id, payload);
    await load();
    showToast('Address updated successfully.');
  };

  const handleSetDefault = async (address) => {
    await withBusy(address.id, async () => {
      await addressService.setDefault(address.id);
      await load();
      showToast('Default address updated.');
    });
  };

  const handleDeleteConfirm = async () => {
    setDeletingBusy(true);
    try {
      await addressService.remove(deleting.id);
      setDeleting(null);
      await load();
      showToast('Address deleted successfully.');
    } catch (err) {
      showToast(getErrorMessage(err), 'danger');
    } finally {
      setDeletingBusy(false);
    }
  };

  return (
    <AccountLayout>
      <Breadcrumbs items={[{ label: 'Home', to: '/' }, { label: 'My Account', to: '/account/profile' }, { label: 'Addresses' }]} />

      <PageHeader title="My Addresses" subtitle="Manage the shipping addresses used at checkout.">
        <Button variant="success" size="sm" onClick={() => { setEditing(null); setShowForm(true); }}>
          <PlusIcon size={16} className="me-1" />
          Add New Address
        </Button>
      </PageHeader>

      {loadError && <Alert variant="danger">{loadError}</Alert>}

      {loading ? (
        <Container className="py-4">
          <LoadingSpinner label="Loading your addresses..." />
        </Container>
      ) : addresses.length === 0 ? (
        <EmptyState
          title="No saved addresses"
          message="Add a shipping address so you can check out quickly."
          actionLabel="Add Address"
          onAction={() => setShowForm(true)}
        />
      ) : (
        <Row className="g-3">
          {addresses.map((address) => (
            <Col xs={12} md={6} lg={4} key={address.id}>
              <AddressCard
                address={address}
                onEdit={handleEdit}
                onDelete={(addr) => setDeleting(addr)}
                onSetDefault={address.is_default ? null : handleSetDefault}
                disabled={busyId !== null}
              />
              {busyId === address.id && (
                <div className="text-center mt-2">
                  <Spinner animation="border" size="sm" />
                </div>
              )}
            </Col>
          ))}
        </Row>
      )}

      <p className="text-muted small mt-4 d-flex align-items-center gap-2">
        <MapPinIcon size={16} />
        The first address you save becomes your default.
      </p>

      <AddressForm
        show={showForm}
        onHide={() => setShowForm(false)}
        initialValues={editing}
        onSubmit={editing ? handleUpdate : handleCreate}
        title={editing ? 'Edit Address' : 'Add Address'}
        submitLabel={editing ? 'Update Address' : 'Save Address'}
      />

      <ConfirmDialog
        show={deleting !== null}
        title="Delete address?"
        message={
          deleting
            ? `Are you sure you want to remove "${deleting.recipient_name} — ${deleting.address_line1}"?`
            : ''
        }
        confirmLabel="Delete"
        busy={deletingBusy}
        onConfirm={handleDeleteConfirm}
        onCancel={() => setDeleting(null)}
      />
    </AccountLayout>
  );
}