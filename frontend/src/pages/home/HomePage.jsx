import usePageTitle from '../../hooks/usePageTitle';
import HeroBanner from '../../components/home/HeroBanner';
import FeatureStrip from '../../components/home/FeatureStrip';
import CategoriesSection from '../../components/home/CategoriesSection';
import MostPopularProducts from '../../components/home/MostPopularProducts';
import PromoBannerRow from '../../components/home/PromoBannerRow';
import BestSellingProducts from '../../components/home/BestSellingProducts';
import AboutUsSection from '../../components/home/AboutUsSection';
import StatsBar from '../../components/home/StatsBar';
import TestimonialsSection from '../../components/home/TestimonialsSection';
import AppDownloadBanner from '../../components/home/AppDownloadBanner';
import BlogSection from '../../components/home/BlogSection';
import BrandLogosStrip from '../../components/home/BrandLogosStrip';
import NewsletterSection from '../../components/home/NewsletterSection';

export default function HomePage() {
  usePageTitle();

  return (
    <>
      <HeroBanner />
      <FeatureStrip />
      <CategoriesSection />
      <MostPopularProducts />
      <PromoBannerRow />
      <BestSellingProducts />
      <AboutUsSection />
      <StatsBar />
      <TestimonialsSection />
      <AppDownloadBanner />
      <BlogSection />
      <BrandLogosStrip />
      <NewsletterSection />
    </>
  );
}
