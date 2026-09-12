import { Link } from 'react-router-dom';
import { Carousel } from 'react-bootstrap';
import { ArrowRightIcon } from '../../assets/icons';

const ABOUT_SLIDES = [
  {
    src: '/storage/category-icons/vegetables.jpg',
    alt: 'Fresh organic vegetables from local farms',
    caption: 'Farm-fresh organic vegetables',
  },
  {
    src: '/storage/category-icons/fruits.jpg',
    alt: 'Seasonal organic fruits',
    caption: 'Seasonal, naturally ripened fruits',
  },
  {
    src: '/storage/category-icons/leafy-greens.jpg',
    alt: 'Organic leafy greens',
    caption: 'Certified organic leafy greens',
  },
];

export default function AboutUsSection() {
  return (
    <section className="section-about py-5">
      <div className="container-lg">
        <div className="about-grid align-items-center">
          <div className="about-image-col">
            <div className="about-image-frame">
              <Carousel
                interval={4500}
                controls={false}
                indicators={true}
                pause="hover"
                className="about-carousel"
              >
                {ABOUT_SLIDES.map((slide) => (
                  <Carousel.Item key={slide.src}>
                    <img
                      className="about-slide-img w-100 h-100"
                      src={slide.src}
                      alt={slide.alt}
                      loading="lazy"
                    />
                    <Carousel.Caption className="d-none d-sm-block">
                      <span className="about-slide-caption">{slide.caption}</span>
                    </Carousel.Caption>
                  </Carousel.Item>
                ))}
              </Carousel>
            </div>
            <div className="about-experience-badge">
              <span className="about-exp-number">15+</span>
              <span className="about-exp-text">Years of Experience</span>
            </div>
          </div>

          <div className="about-content-col">
            <span className="about-chip">About Us</span>
            <h2 className="about-title">
              We Are The Best Organic Food Provider
            </h2>
            <p className="about-text">
              We are committed to providing the freshest, highest quality organic
              produce directly from local farms to your table. Our mission is to make
              healthy eating accessible, convenient, and enjoyable for everyone.
            </p>
            <div className="about-features">
              <div className="about-feature-item">
                <span className="about-feature-check">&#10003;</span>
                <span>100% Certified Organic Products</span>
              </div>
              <div className="about-feature-item">
                <span className="about-feature-check">&#10003;</span>
                <span>Farm Fresh Quality Guaranteed</span>
              </div>
              <div className="about-feature-item">
                <span className="about-feature-check">&#10003;</span>
                <span>Free Delivery on Orders Over $50</span>
              </div>
            </div>
            <Link to="/about" className="btn btn-custom-orange mt-3">
              Learn More <ArrowRightIcon size={16} />
            </Link>
          </div>
        </div>
      </div>
    </section>
  );
}