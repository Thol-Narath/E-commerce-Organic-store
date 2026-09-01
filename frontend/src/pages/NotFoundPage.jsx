import { Button, Container } from 'react-bootstrap';
import EmptyState from '../components/common/EmptyState';
import usePageTitle from '../hooks/usePageTitle';
import { Link } from 'react-router-dom';

export default function NotFoundPage() {
  usePageTitle('Page Not Found');

  return (
    <Container className="py-5">
      <EmptyState
        title="404 — Page Not Found"
        message="The page you are looking for does not exist. It may have moved, or the address may be incorrect."
      />
      <div className="text-center mt-3 d-flex justify-content-center gap-2">
        <Button as={Link} to="/" variant="outline-success">Go to Home</Button>
        <Button as={Link} to="/shop" variant="success">Browse the Shop</Button>
      </div>
    </Container>
  );
}