import { Component } from 'react';
import { Alert, Button, Container } from 'react-bootstrap';

/**
 * Catches render/lifecycle errors in the subtree and shows a fallback UI
 * instead of unmounting the whole React tree (which would blank the screen).
 */
export default class ErrorBoundary extends Component {
  constructor(props) {
    super(props);
    this.state = { hasError: false, message: '' };
  }

  static getDerivedStateFromError(error) {
    return { hasError: true, message: error?.message || 'Something went wrong.' };
  }

  componentDidCatch(error, info) {
    if (typeof this.props.onError === 'function') {
      this.props.onError(error, info);
    }
  }

  handleReset = () => {
    this.setState({ hasError: false, message: '' });
  };

  render() {
    if (this.state.hasError) {
      return (
        <Container className="py-5">
          <Alert variant="danger" className="text-center">
            <h4 className="mb-3">Something went wrong</h4>
            <p className="mb-4">{this.state.message}</p>
            <Button variant="danger" onClick={this.handleReset}>
              Try again
            </Button>
          </Alert>
        </Container>
      );
    }

    return this.props.children;
  }
}
