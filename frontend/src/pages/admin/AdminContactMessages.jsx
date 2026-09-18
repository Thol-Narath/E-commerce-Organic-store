import { useCallback, useEffect, useState } from 'react';
import {
  Alert,
  Badge,
  Button,
  Form,
  InputGroup,
  Row,
  Spinner,
  Table,
  Modal,
} from 'react-bootstrap';
import { adminContactService } from '../../services/adminContactService';
import StorePagination from '../../components/common/StorePagination';
import usePageTitle from '../../hooks/usePageTitle';
import { formatDate } from '../../utils/format';
import { getErrorMessage } from '../../utils/error';
import { SearchIcon, MailIcon } from '../../assets/icons';

const FILTER_OPTIONS = [
  { value: 'all', label: 'All messages' },
  { value: 'unreplied', label: 'Needs reply' },
  { value: 'unread', label: 'Unread' },
  { value: 'read', label: 'Read' },
  { value: 'replied', label: 'Replied' },
];

const PER_PAGE = 15;

export default function AdminContactMessages() {
  usePageTitle('Messages');

  const [filters, setFilters] = useState({ search: '', status: 'all' });
  const [message, setMessage] = useState('');
  const [page, setPage] = useState(1);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');

  const [selected, setSelected] = useState(null);
  const [detailLoading, setDetailLoading] = useState(false);
  const [detailError, setDetailError] = useState('');
  const [replyText, setReplyText] = useState('');
  const [savingReply, setSavingReply] = useState(false);
  const [replyError, setReplyError] = useState('');
  const [replySuccess, setReplySuccess] = useState('');

  const setFilter = (key, value) => {
    setFilters((prev) => ({ ...prev, [key]: value }));
    setPage(1);
  };

  const load = useCallback(async () => {
    setLoading(true);
    setError('');
    try {
      const data = await adminContactService.list({
        page,
        per_page: PER_PAGE,
        q: filters.search || undefined,
        status: filters.status || undefined,
      });
      setMessage(data);
    } catch (err) {
      setError(getErrorMessage(err));
    } finally {
      setLoading(false);
    }
  }, [page, filters.search, filters.status]);

  useEffect(() => {
    load();
  }, [load]);

  const messages = message?.messages ?? [];
  const pagination = message?.pagination ?? {};
  const counts = message?.counts ?? {};

  const refreshList = async () => {
    const data = await adminContactService.list({
      page,
      per_page: PER_PAGE,
      q: filters.search || undefined,
      status: filters.status || undefined,
    });
    setMessage(data);
  };

  const openDetail = async (id) => {
    setSelected(null);
    setDetailLoading(true);
    setDetailError('');
    setReplyError('');
    setReplySuccess('');
    setReplyText('');
    try {
      const detail = await adminContactService.get(id);
      setSelected(detail);
    } catch (err) {
      setDetailError(getErrorMessage(err));
    } finally {
      setDetailLoading(false);
    }
  };

  const toggleRead = async (item, read) => {
    try {
      const updated = await adminContactService.markRead(item.id, read);
      if (selected?.id === item.id) setSelected(updated);
      await refreshList();
    } catch (err) {
      setDetailError(getErrorMessage(err));
    }
  };

  const handleReply = async (e) => {
    e.preventDefault();
    if (!replyText.trim() || savingReply) return;
    setSavingReply(true);
    setReplyError('');
    setReplySuccess('');
    try {
      const res = await adminContactService.reply(selected.id, replyText.trim());
      setSelected(res.data);
      setReplyText('');
      setReplySuccess(res.message || 'Reply sent.');
      await refreshList();
    } catch (err) {
      setReplyError(getErrorMessage(err));
    } finally {
      setSavingReply(false);
    }
  };

  const handleDelete = async (item) => {
    if (!window.confirm(`Delete the message from ${item.name}?`)) return;
    try {
      await adminContactService.remove(item.id);
      setSelected(null);
      await refreshList();
      if (messages.length === 1 && page > 1) setPage(page - 1);
      else await load();
    } catch (err) {
      setError(getErrorMessage(err));
    }
  };

  return (
    <div>
      <div className="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
        <div>
          <h2 className="h4 mb-1">Contact Messages</h2>
          {counts.unread > 0 && (
            <div className="text-muted small">
              {counts.unread} unread · {counts.unreplied} awaiting a reply
            </div>
          )}
        </div>
      </div>

      <Row className="g-2 mb-3">
        <div className="col-md-5 col-lg-4">
          <InputGroup>
            <InputGroup.Text className="bg-white">
              <SearchIcon size={16} className="text-muted" />
            </InputGroup.Text>
            <Form.Control
              type="search"
              placeholder="Search by name, email or subject..."
              value={filters.search}
              onChange={(e) => setFilter('search', e.target.value)}
            />
          </InputGroup>
        </div>
        <div className="col-md-4 col-lg-3">
          <Form.Select
            value={filters.status}
            onChange={(e) => setFilter('status', e.target.value)}
            aria-label="Filter messages by status"
          >
            {FILTER_OPTIONS.map((opt) => (
              <option key={opt.value} value={opt.value}>
                {opt.label}
              </option>
            ))}
          </Form.Select>
        </div>
      </Row>

      {error && <Alert variant="danger">{error}</Alert>}

      {loading ? (
        <div className="text-center py-5">
          <Spinner animation="border" variant="success" />
        </div>
      ) : messages.length === 0 ? (
        <Alert variant="info">
          <MailIcon size={18} className="me-2" />
          No contact messages found.
        </Alert>
      ) : (
        <>
          <div className="table-responsive">
            <Table hover striped>
              <thead>
                <tr>
                  <th style={{ width: '36px' }}></th>
                  <th>From</th>
                  <th>Subject</th>
                  <th>Status</th>
                  <th>Received</th>
                </tr>
              </thead>
              <tbody>
                {messages.map((m) => (
                  <tr key={m.id} className={!m.is_read ? 'fw-semibold' : ''} style={{ cursor: 'pointer' }} onClick={() => openDetail(m.id)}>
                    <td>
                      {!m.is_read && (
                        <span className="badge bg-success rounded-circle p-2" title="Unread">
                          <span className="visually-hidden">Unread</span>
                        </span>
                      )}
                    </td>
                    <td>
                      <div>{m.name}</div>
                      <div className="text-muted small fw-normal">{m.email}</div>
                    </td>
                    <td>{m.subject}</td>
                    <td>
                      <div className="d-flex flex-column gap-1 align-items-start">
                        {!m.is_replied ? (
                          <Badge bg="warning" text="dark">Needs reply</Badge>
                        ) : (
                          <Badge bg="success">Replied</Badge>
                        )}
                        {!m.is_read && <Badge bg="secondary">Unread</Badge>}
                      </div>
                    </td>
                    <td className="text-muted small">{formatDate(m.created_at)}</td>
                  </tr>
                ))}
              </tbody>
            </Table>
          </div>

          {pagination.last_page > 1 && (
            <StorePagination
              pagination={pagination}
              onPageChange={setPage}
              disabled={loading}
              ariaLabel="Contact messages pagination"
            />
          )}
        </>
      )}

      <Modal show={!!selected} onHide={() => setSelected(null)} size="lg" centered>
        <Modal.Header closeButton>
          <Modal.Title>Message reply</Modal.Title>
        </Modal.Header>
        <Modal.Body>
          {detailLoading ? (
            <div className="text-center py-4">
              <Spinner animation="border" variant="success" />
            </div>
          ) : detailError ? (
            <Alert variant="danger">{detailError}</Alert>
          ) : selected ? (
            <>
              <div className="mb-3 pb-3 border-bottom">
                <h5 className="mb-1">{selected.subject}</h5>
                <div className="text-muted small">
                  {selected.name} &lt;{selected.email}&gt; · {formatDate(selected.created_at)}
                </div>
                {selected.user && (
                  <span className="text-muted small">
                    Registered customer{selected.user.email ? ` · ${selected.user.email}` : ''}
                  </span>
                )}
              </div>

              <div className="p-3 bg-light rounded mb-3" style={{ whiteSpace: 'pre-wrap' }}>
                {selected.message}
              </div>

              {selected.is_replied && (
                <div className="mb-3">
                  <div className="text-muted small mb-1">
                    Your reply{selected.replied_by ? ` (${selected.replied_by.name})` : ''} ·{' '}
                    {selected.replied_at ? formatDate(selected.replied_at) : ''}
                  </div>
                  <div className="p-3 bg-success-subtle rounded" style={{ whiteSpace: 'pre-wrap' }}>
                    {selected.reply}
                  </div>
                </div>
              )}

              <Form onSubmit={handleReply}>
                <Form.Group controlId="contactReply" className="mb-2">
                  <Form.Label>Reply to {selected.name}</Form.Label>
                  <Form.Control
                    as="textarea"
                    rows={4}
                    placeholder={selected.is_replied ? 'Send an updated reply...' : 'Type your reply...'}
                    value={replyText}
                    onChange={(e) => setReplyText(e.target.value)}
                    disabled={savingReply}
                  />
                </Form.Group>
                {replyError && <Alert variant="danger" className="py-2">{replyError}</Alert>}
                {replySuccess && <Alert variant="success" className="py-2">{replySuccess}</Alert>}
                <div className="d-flex flex-wrap gap-2 justify-content-between align-items-center">
                  <div className="d-flex gap-2">
                    {selected.is_read ? (
                      <Button variant="outline-secondary" size="sm" onClick={() => toggleRead(selected, false)}>
                        Mark as unread
                      </Button>
                    ) : (
                      <Button variant="outline-secondary" size="sm" onClick={() => toggleRead(selected, true)}>
                        Mark as read
                      </Button>
                    )}
                    <Button variant="outline-danger" size="sm" onClick={() => handleDelete(selected)}>
                      Delete
                    </Button>
                  </div>
                  <Button type="submit" variant="success" disabled={savingReply || !replyText.trim()}>
                    {savingReply ? (
                      <>
                        <Spinner as="span" animation="border" size="sm" className="me-2" /> Sending…
                      </>
                    ) : (
                      'Send reply'
                    )}
                  </Button>
                </div>
              </Form>
            </>
          ) : null}
        </Modal.Body>
      </Modal>
    </div>
  );
}